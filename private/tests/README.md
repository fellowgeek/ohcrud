# Security regression tests

[Documentation](../../docs/DOCUMENTATION.md) · [Deployment and testing](../../docs/deployment-and-testing.md)

These tests require PHP with PDO SQLite, GD and the installed Composer dependencies,
Python 3, Node.js, and Hurl. They never load local application settings or use the application database.

Run all security regressions from the repository root with `python3 private/tests/run.py`.
Each HTTP suite receives its own disposable database. The security Hurl files live in
`private/hurl/security/` so the legacy `private/hurl/*.hurl` command is unchanged.

To run individual checks:

```sh
php private/tests/component.php
php private/tests/login.php
python3 private/tests/http_tests.py private/tests/totp.php private/hurl/security/security_totp.hurl
python3 private/tests/http_tests.py private/hurl/security/security_auth.hurl
python3 private/tests/http_tests.py private/hurl/security/security_login.hurl private/hurl/security/security_cache.hurl
```

The HTTP runner creates and removes a temporary application copy, SQLite database,
sessions, cache and logs. It uses loopback HTTP, so Secure-cookie and reverse-proxy
behavior still require deployment-level HTTPS checks. Run suites that change account
state separately; `security_auth.hurl` deliberately disables its test MFA account.
TOTP expiry and attempt-window tests use a fixed clock; Hurl uses a freshly generated
code for the disposable account.

## Full Hurl suite over HTTPS

Run every Hurl file, including the legacy success/failure suites, from the repository root:

```sh
python3 private/tests/full_hurl.py
```

This runner also requires Docker, OpenSSL, the built `ohcrud-php` image
(`docker compose build php`), and the Nginx image used by Compose. It starts separate
Nginx/PHP containers with the repository's Nginx configuration, a temporary TLS
certificate, and a loopback-only random HTTPS port. Hurl skips certificate verification
for that disposable certificate; cookies still use HTTPS and their Secure setting.
Each suite gets fresh SQLite, session, cache, log and upload fixtures. Containers,
the temporary network and fixture files are removed afterward.

The legacy suites receive an `admin/admin` account and an allowed
`https://example.com` origin in this test fixture only. Application settings and
databases are never loaded or changed. To run just the legacy suites:

```sh
python3 private/tests/full_hurl.py private/hurl/test_passes.hurl private/hurl/test_fails.hurl
```

## Deployment migration

`Users` automatically creates a missing `AuthState` table when debug mode is on,
including when the `Users` table already exists. Explicit CLI schema provisioning
(`new Users(true)`, used by `private/create-admin.php`) also creates it. Repeated
initialization preserves existing counters and replay state on SQLite and MySQL.

With debug mode off, ordinary requests do not create tables. For installations
provisioned directly in production, run `php private/migrate.php` in the configured
application environment before deploying; it provisions framework tables and applies
`private/migrations/001-auth-state.sql` through tracked migration history. Missing authentication state
fails authentication closed. Do not grant the runtime account additional schema
privileges just for this migration.

Existing sessions are invalidated on deployment because they lack the new security
fingerprint. Password, status, permissions, username, token or TOTP changes invalidate
sessions and pending challenges. Sessions have an absolute and idle limit equal to
`__OHCRUD_SESSION_LIFETIME__`; TOTP challenges last five minutes.

Password attempts are limited to ten per account per fifteen minutes, TOTP attempts
to five, and failed authentication attempts to one hundred per source IP. Successful full
authentication clears the account counters, but not unrelated source-IP failures. Successful credentials release their own source-IP attempt reservation. Replay
protection atomically consumes each accepted TOTP time step across sessions. API
tokens intentionally authenticate without TOTP.

CMS rendered HTML is no longer shared through the application cache: components and
embedded pages may depend on the viewer's current authorization. Purified Markdown fragments may be cached by content and renderer version after
checking current page permissions and status. Components, embedded content and
session output are still rendered on each request. Core cache helpers remain
available for application data that is safe to share.

## Authenticator management

Signed-in users manage their own authenticator at `/security/`. Enrollment and
replacement require the current password and, if enabled, a fresh code from the
existing authenticator. The replacement remains pending for five minutes and is
activated only after a correct code from it. Factor removal requires the same
credentials. Existing secrets are not redisplayed; old admin QR/reset buttons now
open the account security page. Generic user-table writes cannot bypass this flow.

The legacy `Users::enableTOTP()` helper now returns false; use `beginTOTP()` and
`confirmTOTP()` in an authenticated session. Administrative lost-factor recovery
requires a separately controlled operational process, rather than an unrestricted
secret-read/reset endpoint. Raw SQL remains a database-administrator capability
when explicitly enabled and must not be granted to untrusted application editors.

## Production configuration

The sample configuration disables debug output and raw SQL execution. Existing
local settings are not rewritten: apply those values deliberately when deploying.
The web bootstrap rejects an unset/short/placeholder application secret. PHP logs
errors privately; the Nginx configuration blocks template/dependency files and
serves non-raster uploads as sandboxed attachments. Reload Nginx and restart PHP
through the deployment process to apply server configuration changes.

Debug schema initialization no longer creates `admin/admin`. For an initial admin,
run `php private/create-admin.php` in the configured application environment with
`OHCRUD_ADMIN_USERNAME`, `OHCRUD_ADMIN_EMAIL`, and `OHCRUD_ADMIN_PASSWORD` supplied
securely through the environment; unset the password afterward. Existing accounts
are preserved. This command provisions both `Users` and `AuthState`; it does not replace the
application's other schema setup.

CMS asset bundles use ordered source paths, size, inode and modification metadata
for versioning. Set `__OHCRUD_CMS_ASSET_VERSION__` to a new release identifier when
deploying assets with preserved metadata (or clear `public/global/minified/`).
In-place edits with identical size and timestamps require the same invalidation.

The migration CLI and its isolated SQLite/MySQL checks are documented in
[Database setup and migrations](../../docs/Migrations.md). The default regression
runner includes the SQLite migration checks; MySQL is an explicit optional run.
