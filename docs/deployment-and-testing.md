# Deployment, testing, and troubleshooting

[Documentation](DOCUMENTATION.md) · [Configuration](getting-started.md#configuration-reference) · [Migrations](Migrations.md)

## Production deployment

1. Back up the database, uploaded media, private configuration, and application secret together. Rehearse restore and retain the migration files corresponding to the backup.
2. Install the locked dependencies in the release artifact:

   ```sh
   composer --working-dir=public install --no-dev --prefer-dist --optimize-autoloader
   composer --working-dir=public check-platform-reqs
   ```

3. Supply `public/settings.php` securely. Keep debug, SQL debug output, and raw admin SQL execution disabled. Keep the existing application secret stable. Configure HTTPS and Secure cookies, and set the correct allowed origins.
4. Provision writable log/cache/session/upload directories and, if enabled, the minified-bundle directory. Keep private files and the SQLite database outside the document root. Use a migration identity with required schema privileges; avoid granting runtime schema privileges just to initialize tables.
5. Inspect migration status and apply pending changes once from the deployment environment:

   ```sh
   php private/migrate.php --status
   php private/migrate.php
   ```

6. Deploy compatible code and reload PHP/server configuration through your process. Update `__OHCRUD_CMS_ASSET_VERSION__` when assets retain metadata. Smoke-test public pages, login, permissions, and an authorized write before considering the release complete.

Migration rollback is not automatic. For MySQL DDL failures, inspect partial changes before retrying; use a forward repair or a rehearsed backup restore. Do not delete migration history to force a rerun. Custom tables should already have audit columns before runtime writes. See [migration guarantees](Migrations.md).

## Web server and proxy contract

The document root is `public/`. Use [private/nginx.conf](../private/nginx.conf) as the implemented server reference and adapt its TLS paths/FPM upstream for your environment. Preserve its protections for templates, dependency files, hidden files, and uploaded executable content. Non-raster downloads use attachment and sandbox headers. The image endpoint has an Nginx rate limit; adapt it if the API base prefix changes.

The repository does not include an Apache `.htaccess` file. An Apache deployment must implement front-controller rewriting and equivalent access/upload protections itself; rewriting alone is insufficient. The PHP smoke server in [getting started](getting-started.md) is only for local API checks.

Behind a proxy, ensure the application sees the intended host/scheme and client address. There is no general trusted-proxy configuration layer in the framework. IP filtering and authentication throttling use `REMOTE_ADDR`; a proxy address shared by every request changes their behavior. Restrict accepted hostnames at the web server because site/domain values derive from the request host.

The supplied Compose topology uses development credentials, a published database port, mutable image tags, and Xdebug. Treat it as development infrastructure, not a production deployment recipe. Provision a production PHP build and database policy deliberately.

## Validation

Run whitespace checks and lint every changed PHP/template file:

```sh
git diff --check
php -l public/app/controllers/cNotes.php
php -l public/app/models/mNotes.php
```

Those example paths exist after following the Notes guide. For Compose, public files map to `/app`:

```sh
docker compose exec -T php php -l /app/app/controllers/cNotes.php
```

Documentation-only changes need relative-link/anchor checks and example validation, not service startup or mutation of application data. For manifest changes, also run `composer --working-dir=public validate`.

### Isolated regression suite

Prerequisites: installed Composer dependencies, PHP with PDO SQLite and GD, Python 3, Node.js, and Hurl. From the repository root:

```sh
python3 private/tests/run.py
```

This runs PHP, JavaScript, HTTP, and SQLite migration checks. HTTP fixtures use temporary application copies, databases, sessions, logs, caches, and uploads; they do not load local settings or use the application database. They use loopback HTTP, so deployment HTTPS/proxy behavior needs separate coverage.

For focused checks:

```sh
php private/tests/router.php
php private/tests/component.php
python3 private/tests/http_tests.py private/hurl/security/security_requests.hurl
python3 private/tests/migrations.py
```

The existing test approach is custom PHP/Python/Node runners and Hurl, not PHPUnit. See [the test runner README](../private/tests/README.md).

### Full HTTPS Hurl suite

Requires Docker, OpenSSL, Hurl, the built `ohcrud-php` image, and Nginx:

```sh
docker compose build php
python3 private/tests/full_hurl.py
```

The runner provisions disposable SQLite fixtures and separate HTTPS containers, including the legacy success/failure suites. The fixture-only `admin/admin` account is not an application default. Temporary certificates are verified through the runner's explicit test configuration; do not carry that certificate policy into production.

MySQL migration coverage is optional and explicit:

```sh
python3 private/tests/migrations.py --mysql
```

It uses the running Compose MariaDB service, creates a unique test database, and removes that database afterward.

### Legacy manual Hurl execution

Prefer the disposable runner. Running `private/hurl/*.hurl` manually requires a configured HTTPS host, seeded test account/database, allowed origins, and upload fixture; these tests create, update, upload, and delete data. Use only a disposable environment:

```sh
cd private/hurl
hurl --test --variable host=ohcrud.test *.hurl
```

The legacy `test.sh` hardcodes `ohcrud.loc`, while Compose uses `ohcrud.test`. Security suites live separately under `private/hurl/security/`. CSRF validation remains active even in debug mode; run security checks with production-style debug settings.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| 503 “Application configuration is incomplete” | Replace placeholder/short secret in settings; do not enable debug as a fix |
| Missing `vendor/autoload.php` or settings | Copy settings for a fresh checkout, then install dependencies in `public/` |
| “could not find driver” / database 500 | Check serving PHP's PDO driver, credentials, network host, schema, and private logs |
| SQLite cannot open/write | Use an absolute file path, existing writable parent, and correct runtime ownership |
| MariaDB fails immediately after Compose startup | Wait for readiness; `depends_on` is not a health check |
| Login fails despite correct password | Run migrations, inspect AuthState availability/throttling, verify account status and stable secret |
| Session disappears on local HTTP | Secure cookie needs HTTPS; disable Secure only for loopback development |
| 404 at a mapped API prefix | Append an explicitly annotated method; there is no default index action |
| 405 | Match the `Endpoint` verb list |
| 403 on browser API calls | Inspect session permissions, Origin/Referer, Fetch Metadata, and IP policy |
| Missing/invalid CSRF | Use the same session's token; JSON writes use `payload.CSRF`, upload uses top-level form `CSRF` |
| CMS path ignores database edits | Check for a higher-priority `.phtml` file at the same path |
| Draft/deleted page is 404 | Publish it or open authenticated edit mode |
| Component/template missing | Check namespace case, component marker, `.phtml` path containment, and deployed files |
| Theme picker empty | Check theme files and public working directory used by `glob('themes/*/*.html')` |
| CSS/JS missing on local PHP smoke server | Use the full HTTPS server configuration to serve static assets |
| Stale minified assets | Change release asset version or clear generated bundles |
| Upload rejected | Check extension/MIME, 16 MiB/pixel limits, PHP/proxy limits, and writable directory |
| Migration history error | Restore original applied files; add forward migrations instead of editing old ones |

When reporting failures, include the route, HTTP status, sanitized error, runtime version, database driver, and minimal reproduction. Keep passwords, tokens, private settings, and full database dumps out of issues and commits.
