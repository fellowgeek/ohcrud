#!/usr/bin/env python3
"""Run Hurl against a temporary application copy and disposable SQLite database."""
import os
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import tempfile
import time

repo = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='ohcrud-http-') as directory:
    root = Path(directory)
    for name in ('app', 'ohcrud', 'themes'):
        shutil.copytree(repo / 'public' / name, root / name)
    (root / 'global' / 'files').mkdir(parents=True)
    (root / 'sessions').mkdir()
    for name in ('index.php', 'routes.php'):
        shutil.copy(repo / 'public' / name, root / name)
    index = (root / 'index.php').read_text().replace("__DIR__ . '/vendor/autoload.php'", repr(str(repo / 'private/tests/autoload.php')))
    (root / 'index.php').write_text(index)
    settings = (repo / 'public/settings.sample.php').read_text()
    settings = settings.replace("define('__OHCRUD_DEBUG_MODE__', true)", "define('__OHCRUD_DEBUG_MODE__', false)")
    settings = settings.replace("'/logs/'", repr(str(root) + '/'))
    settings = settings.replace("define('__OHCRUD_SESSION_SECURE_COOKIE__', true)", "define('__OHCRUD_SESSION_SECURE_COOKIE__', false)")
    settings = settings.replace("$_SERVER['HTTP_HOST'] ?? ''", "'127.0.0.1'")
    settings = settings.replace('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', 'isolated-test-secret')
    settings = settings.replace("'DRIVER' => 'MYSQL'", "'DRIVER' => 'SQLITE'")
    settings = settings.replace("'database_filename.db'", repr(str(root / 'test.sqlite')))
    (root / 'settings.php').write_text(settings)
    env = dict(os.environ, OHCRUD_TEST_ROOT=str(root))
    subprocess.run(['php', str(repo / 'private/tests/seed.php')], env=env, check=True)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    with (root / 'server.log').open('w+') as log:
        server = subprocess.Popen(['php', '-d', 'session.save_path=' + str(root / 'sessions'), '-S', f'127.0.0.1:{port}', '-t', str(root), str(root / 'index.php')], cwd=root, env=env, stdout=log, stderr=log)
        try:
            for _ in range(100):
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=0.1):
                        break
                except OSError:
                    time.sleep(0.05)
            files = sys.argv[1:] or [str(repo / 'private/hurl/security_login.hurl')]
            subprocess.run(['hurl', '--test', '--variable', f'base=http://127.0.0.1:{port}', *files], check=True)
        except Exception:
            log.seek(0)
            print(log.read(), file=sys.stderr)
            if (root / 'app.log').exists():
                print((root / 'app.log').read_text(), file=sys.stderr)
            raise
        finally:
            server.terminate()
            server.wait(timeout=10)
