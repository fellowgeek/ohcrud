#!/usr/bin/env python3
"""Exercise the actual migration CLI with isolated configuration and database state."""
from pathlib import Path
import fcntl
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import uuid

repo = Path(__file__).resolve().parents[2]
mysql = '--mysql' in sys.argv
config = {'DRIVER': 'SQLITE', 'PERSISTENT_CONNECTION': False, 'USERNAME': '', 'PASSWORD': ''}
if mysql:
    # Read only the disposable development service credential; never print it.
    password = subprocess.check_output(['docker', 'compose', 'exec', '-T', 'mysql', 'printenv', 'MYSQL_ROOT_PASSWORD'], cwd=repo, text=True).strip()
    config.update(DRIVER='MYSQL', MYSQL_HOST='127.0.0.1', MYSQL_DB='ohcrud_migration_test_' + uuid.uuid4().hex[:12], USERNAME='root', PASSWORD=password)
query_code = r'''
$c = json_decode(getenv('OHCRUD_MIGRATION_TEST_CONFIG'), true);
$dsn = $c['DRIVER'] === 'SQLITE' ? 'sqlite:' . $c['SQLITE_DB'] : 'mysql:host=' . $c['MYSQL_HOST'] . ';dbname=' . $c['MYSQL_DB'];
$db = new PDO($dsn, $c['USERNAME'], $c['PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo json_encode($db->query(stream_get_contents(STDIN))->fetchAll(PDO::FETCH_ASSOC));
'''
with tempfile.TemporaryDirectory(prefix='ohcrud-migrations-') as directory:
    root = Path(directory)
    public = root / 'public'
    private = root / 'private'
    public.mkdir()
    private.mkdir()
    for name in ('app', 'ohcrud'):
        shutil.copytree(repo / 'public' / name, public / name)
    shutil.copy(repo / 'public/routes.php', public / 'routes.php')
    shutil.copy(repo / 'private/migrate.php', private / 'migrate.php')
    shutil.copytree(repo / 'private/migrations', private / 'migrations')
    (public / 'vendor').mkdir()
    (public / 'vendor/autoload.php').write_text('<?php require ' + repr(str(repo / 'private/tests/autoload.php')) + ';')
    config['SQLITE_DB'] = str(root / 'test.sqlite')
    settings = (repo / 'public/settings.sample.php').read_text()
    settings = re.sub(r"define\('__OHCRUD_DB_CONFIG__', \[[\s\S]*?\]\s*\);", "define('__OHCRUD_DB_CONFIG__', json_decode(getenv('OHCRUD_MIGRATION_TEST_CONFIG'), true));", settings)
    settings = settings.replace("'/logs/'", repr(str(root) + '/'))
    settings = settings.replace('XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', 'isolated-test-secret-at-least-32-characters')
    (public / 'settings.php').write_text(settings)
    env = dict(os.environ, OHCRUD_TEST_ROOT=str(public), OHCRUD_PUBLIC_PATH=str(public), OHCRUD_MIGRATION_TEST_CONFIG=json.dumps(config))
    command = ['php', str(private / 'migrate.php')]
    def run(*args, success=True):
        result = subprocess.run([*command, *args], env=env, text=True, capture_output=True)
        if (result.returncode == 0) != success:
            raise AssertionError(result.stdout + result.stderr)
        return result.stdout + result.stderr
    def query(sql):
        return json.loads(subprocess.check_output(['php', '-r', query_code], env=env, input=sql, text=True))
    try:
        assert 'not been initialized' in run('--status')
        assert not (root / 'test.sqlite').exists()
        assert '2 migration(s) applied' in run()
        for table in ('Users', 'Pages', 'Files', 'AuthState', 'Migrations'):
            query('SELECT COUNT(*) FROM ' + table)
        assert query('SELECT COUNT(*) AS n FROM Users')[0]['n'] == 0
        assert query('SELECT COUNT(*) AS n FROM Pages')[0]['n'] == 1
        for table in ('Users', 'Pages', 'Files'):
            query('SELECT CDATE, MDATE, CUSER, MUSER FROM ' + table)
        query("UPDATE Pages SET TITLE = 'Preserved page'")
        assert '0 migration(s) applied' in run()
        assert query('SELECT TITLE FROM Pages')[0]['TITLE'] == 'Preserved page'
        assert 'Applied: 002-audit-columns.php' in run('--status')
        third = private / 'migrations/003-fixture.sql'
        third.write_text('CREATE TABLE MigrationFixture (ID INTEGER PRIMARY KEY); INSERT INTO MigrationFixture VALUES (1);')
        assert 'Pending: 003-fixture.sql' in run('--status')
        assert '1 migration(s) applied' in run()
        assert '0 migration(s) applied' in run()
        assert query('SELECT COUNT(*) AS n FROM MigrationFixture')[0]['n'] == 1
        failed = private / 'migrations/004-failure.sql'
        failed.write_text('CREATE TABLE PartialMigration (ID INTEGER); INVALID SQL;')
        assert '004-failure.sql' in run(success=False)
        assert query("SELECT COUNT(*) AS n FROM Migrations WHERE NAME = '004-failure.sql'")[0]['n'] == 0
        if not mysql:
            assert query("SELECT COUNT(*) AS n FROM sqlite_master WHERE name = 'PartialMigration'")[0]['n'] == 0
        failed.write_text('CREATE TABLE IF NOT EXISTS PartialMigration (ID INTEGER);')
        assert '1 migration(s) applied' in run()
        original = third.read_text()
        third.write_text(original + '\n-- altered after application')
        assert 'modified' in run('--status', success=False)
        third.write_text(original)
        if not mysql:
            with open(config['SQLITE_DB'] + '.migration.lock', 'w') as lock:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
                assert 'holds the database lock' in run(success=False)
        older = private / 'migrations/000-older.sql'
        older.write_text('SELECT 1;')
        assert 'precedes applied history' in run(success=False)
        older.unlink()
        duplicate = private / 'migrations/003-duplicate.sql'
        duplicate.write_text('SELECT 1;')
        assert 'Duplicate migration version' in run(success=False)
        duplicate.unlink()
        third.unlink()
        assert 'missing or modified' in run('--status', success=False)
        print(config['DRIVER'] + ' CLI setup, repeat runs, audit fields, migrations, failure recovery and history checks passed.')
    finally:
        if mysql:
            cleanup = r"$c=json_decode(getenv('OHCRUD_MIGRATION_TEST_CONFIG'),true); $db=new PDO('mysql:host='.$c['MYSQL_HOST'],$c['USERNAME'],$c['PASSWORD']); $db->exec('DROP DATABASE `'.$c['MYSQL_DB'].'`');"
            subprocess.run(['php', '-r', cleanup], env=env, check=True, capture_output=True)
