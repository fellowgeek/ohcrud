# Security regression tests

These tests require PHP with PDO SQLite, GD and the installed Composer dependencies,
Python 3, and Hurl. They never load local application settings or use the application database.

Run from the repository root:

```sh
php private/tests/component.php
php private/tests/login.php
python3 private/tests/http.py private/tests/totp.php private/hurl/security_totp.hurl
python3 private/tests/http.py private/hurl/security_auth.hurl
python3 private/tests/http.py private/hurl/security_login.hurl private/hurl/security_cache.hurl
```

The HTTP runner creates and removes a temporary application copy, SQLite database,
sessions, cache and logs. It uses loopback HTTP, so Secure-cookie and reverse-proxy
behavior still require deployment-level HTTPS checks. Run suites that change account
state separately; `security_auth.hurl` deliberately disables its test MFA account.
TOTP expiry and attempt-window tests use a fixed clock; Hurl uses a freshly generated
code for the disposable account.

## Deployment migration

Apply `private/migrations/001-auth-state.sql` before deploying the authentication
changes, using the database's normal migration process. The SQL supports SQLite and
MySQL. Missing authentication state fails authentication closed. Do not grant the
runtime account additional schema privileges just for this migration.

Existing sessions are invalidated on deployment because they lack the new security
fingerprint. Password, status, permissions, username, token or TOTP changes invalidate
sessions and pending challenges. Sessions have an absolute and idle limit equal to
`__OHCRUD_SESSION_LIFETIME__`; TOTP challenges last five minutes.

Password attempts are limited to ten per account per fifteen minutes, TOTP attempts
to five, and authentication attempts to one hundred per source IP. Successful full
authentication clears the account counters, but not the source-IP counter. Replay
protection atomically consumes each accepted TOTP time step across sessions. API
tokens intentionally authenticate without TOTP.

CMS rendered HTML is no longer shared through the application cache: components and
embedded pages may depend on the viewer's current authorization. Core cache helpers
remain available for application data that is safe to share.
