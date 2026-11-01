#!/usr/bin/env python3
"""Run every Hurl suite through isolated HTTPS Nginx/PHP containers and SQLite fixtures."""
from pathlib import Path
import base64
import hashlib
import hmac
import os
import shutil
import socket
import struct
import subprocess
import sys
import tempfile
import time
import uuid

repo = Path(__file__).resolve().parents[2]
suites = [Path(name).resolve() for name in sys.argv[1:]] or sorted((repo / 'private/hurl').rglob('*.hurl'))
identifier = 'ohcrud-hurl-' + uuid.uuid4().hex[:10]
def docker(*args):
    return subprocess.check_output(['docker', *args], cwd=repo, text=True, stderr=subprocess.STDOUT).strip()

with tempfile.TemporaryDirectory(prefix=identifier + '-') as directory:
    root = Path(directory)
    for name in ('app', 'ohcrud', 'themes', 'global'):
        shutil.copytree(repo / 'public' / name, root / name, ignore=shutil.ignore_patterns('files', 'minified'))
    for name in ('global/files', 'sessions', 'cache', 'logs', 'ssl'):
        (root / name).mkdir(parents=True, exist_ok=True)
    index = (repo / 'public/index.php').read_text().replace("__DIR__ . '/vendor/autoload.php'", "'/repository/private/tests/autoload.php'")
    (root / 'index.php').write_text(index)
    settings = (repo / 'public/settings.sample.php').read_text()
    settings = settings.replace("define('__OHCRUD_DEBUG_MODE__', true)", "define('__OHCRUD_DEBUG_MODE__', false)")
    settings = settings.replace("'/logs/'", "'/app/logs/'").replace("'/ramdisk/'", "'/app/cache/'")
    settings = settings.replace("define('__OHCRUD_CACHE_ENABLED__', false)", "define('__OHCRUD_CACHE_ENABLED__', true)")
    settings = settings.replace('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', 'isolated-test-secret-at-least-32-characters')
    settings = settings.replace("'DRIVER' => 'MYSQL'", "'DRIVER' => 'SQLITE'")
    settings = settings.replace("'database_filename.db'", "'/app/test.sqlite'")
    (root / 'settings.php').write_text(settings)
    routes = (repo / 'public/routes.php').read_text().replace("    'http://localhost',", "    'http://localhost',\n    'https://example.com',")
    (root / 'routes.php').write_text(routes)
    shutil.copy(repo / 'private/nginx.conf', root / 'nginx.conf')
    subprocess.run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1', '-subj', '/CN=localhost',
                    '-keyout', str(root / 'ssl/key.pem'), '-out', str(root / 'ssl/cert.pem')], check=True, capture_output=True)
    # Only the disposable fixture is writable by the PHP worker.
    for path in [root, *root.rglob('*')]:
        path.chmod(0o777 if path.is_dir() else 0o666)
    created_network = False
    containers = []
    failed = []
    try:
        docker('network', 'create', identifier)
        created_network = True
        php_name = identifier + '-php'
        docker('run', '-d', '--name', php_name, '--network', identifier, '--network-alias', 'php',
               '-e', 'OHCRUD_TEST_ROOT=/app', '-v', f'{root}:/app', '-v', f'{repo}:/repository:ro', '-w', '/app', 'ohcrud-php',
               'php-fpm', '-d', 'session.save_path=/app/sessions')
        containers.append(php_name)
        web_name = identifier + '-web'
        docker('run', '-d', '--name', web_name, '--network', identifier, '-p', '127.0.0.1::443',
               '-v', f'{root}:/app:ro', '-v', f'{root / "ssl"}:/ssl:ro',
               '-v', f'{root / "nginx.conf"}:/etc/nginx/conf.d/default.conf:ro', 'nginx:latest')
        containers.append(web_name)
        port = int(docker('port', web_name, '443/tcp').rsplit(':', 1)[1])
        for attempt in range(100):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=0.1):
                    break
            except OSError:
                time.sleep(0.1)
        for suite in suites:
            # Separate databases, sessions and caches prevent account-state leakage between suites.
            (root / 'test.sqlite').unlink(missing_ok=True)
            for name in ('sessions', 'cache', 'logs', 'global/files'):
                for path in (root / name).iterdir():
                    if path.is_file():
                        path.unlink()
            env = dict(os.environ, OHCRUD_TEST_ROOT=str(root))
            subprocess.run(['php', str(repo / 'private/tests/seed.php')], env=env, check=True)
            # Legacy suites intentionally use the old sample credentials, in this fixture only.
            if suite.parent == repo / 'private/hurl':
                seed_admin = r"$db=new PDO('sqlite:'.getenv('OHCRUD_TEST_ROOT').'/test.sqlite'); $q=$db->prepare('INSERT INTO Users (ID, USERNAME, PASSWORD, PERMISSIONS, STATUS, TOTP) VALUES (4, ?, ?, 1, 1, 0)'); $q->execute(['admin', password_hash('admin'.'isolated-test-secret-at-least-32-characters', PASSWORD_BCRYPT, ['cost'=>4])]);"
                subprocess.run(['php', '-r', seed_admin], env=env, check=True)
            for path in root.rglob('*'):
                path.chmod(0o777 if path.is_dir() else 0o666)
            # Avoid generating an MFA code at the end of its time step.
            if time.time() % 30 > 26:
                time.sleep(30 - time.time() % 30)
            digest = hmac.new(base64.b32decode('JBSWY3DPEHPK3PXP'), struct.pack('>Q', int(time.time()) // 30), hashlib.sha1).digest()
            offset = digest[-1] & 15
            code = f'{(struct.unpack(">I", digest[offset:offset + 4])[0] & 0x7fffffff) % 1000000:06d}'
            result = subprocess.run(['hurl', '--test', '--insecure', '--file-root', str(repo / 'private/hurl'),
                                     '--variable', f'host=127.0.0.1:{port}', '--variable', f'base=https://127.0.0.1:{port}',
                                     '--variable', f'totp={code}', str(suite)], text=True, capture_output=True)
            print(('PASS ' if result.returncode == 0 else 'FAIL ') + str(suite.relative_to(repo)), flush=True)
            lines = result.stderr.splitlines()
            for i, line in enumerate(lines):
                if line.startswith('error:'):
                    print('\n'.join(lines[i:i + 12]), flush=True)
                if line.startswith(('Executed requests:', 'Succeeded files:', 'Failed files:')):
                    print(line, flush=True)
            if result.returncode != 0:
                failed.append(str(suite.relative_to(repo)))
        print(f'{len(suites) - len(failed)}/{len(suites)} HTTPS Hurl suites passed.', flush=True)
        if failed:
            raise SystemExit(1)
    finally:
        for container in reversed(containers):
            subprocess.run(['docker', 'rm', '-f', container], capture_output=True)
        if created_network:
            subprocess.run(['docker', 'network', 'rm', identifier], capture_output=True)
