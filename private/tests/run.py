#!/usr/bin/env python3
"""Run every security regression with isolated HTTP fixtures for each suite."""
from pathlib import Path
import subprocess
import sys

repo = Path(__file__).resolve().parents[2]
for name in ('component', 'login', 'csrf', 'bootstrap', 'output', 'router', 'cache'):
    subprocess.run(['php', f'private/tests/{name}.php'], cwd=repo, check=True)
subprocess.run(['php', 'private/tests/bootstrap.php', 'production'], cwd=repo, check=True)
subprocess.run(['node', 'private/tests/admin-output.js'], cwd=repo, check=True)
subprocess.run(['node', 'private/tests/http-request.js'], cwd=repo, check=True)
subprocess.run([sys.executable, 'private/tests/http_tests.py', 'private/tests/totp.php'], cwd=repo, check=True)
subprocess.run([sys.executable, 'private/tests/http_tests.py', 'private/tests/cms-cache.php'], cwd=repo, check=True)
subprocess.run([sys.executable, 'private/tests/http_tests.py', 'private/tests/admin-pagination.php'], cwd=repo, check=True)
for suite in sorted((repo / 'private/hurl/security').glob('*.hurl')):
    subprocess.run([sys.executable, 'private/tests/http_tests.py', str(suite)], cwd=repo, check=True)
print('All security regression suites passed.')
