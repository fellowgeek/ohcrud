# Getting started

[Documentation](DOCUMENTATION.md) · Next: [five-minute quickstart](quickstart.md)

## Prerequisites

| Requirement | Purpose |
| --- | --- |
| PHP 8.1+ for the checked-in dependency lock | Monolog and OTPHP dependencies require at least 8.1; the Compose image uses 8.5 |
| Composer | Install `public/composer.lock` dependencies and PSR-4 autoloading |
| PDO plus `pdo_sqlite` or `pdo_mysql` | SQLite files or MySQL/MariaDB connections |
| OpenSSL, sessions, JSON, PCRE, hash | Encryption, authentication, requests, and rendering |
| Fileinfo and GD with JPEG/PNG/GIF/WebP support | Upload inspection and image processing |
| Mbstring | Unicode handling; check your PHP build and dependency requirements |
| Writable private log/cache/session directories | Runtime state; keep these outside the document root |
| Writable `public/global/files/` | Uploaded media |

The framework does not use the separate SQLite3 class extension for its database layer. Curl is useful for the smoke checks below, but the default framework HTTP path does not require PHP's cURL extension. Optional dependency integrations can have additional requirements. Run Composer's platform check in the actual serving runtime as well as on the host.

## Configure a local checkout

```sh
git clone https://github.com/fellowgeek/ohcrud.git
cd ohcrud
cp public/settings.sample.php public/settings.php
composer --working-dir=public install
composer --working-dir=public check-platform-reqs
php -m
```

Only copy settings for a new checkout; preserve an existing installation's configuration. Composer autoloads `settings.php` and `routes.php`, so create settings before running framework commands. There is no automatic `.env` support. To use deployment environment variables, read them explicitly in your settings file and validate missing values.

Generate an application secret:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Replace `__OHCRUD_SECRET__` with that value. Keep it private and stable: passwords and encrypted tokens depend on it. With debug disabled, the web bootstrap rejects the sample placeholder or a secret shorter than 32 characters with HTTP 503.

### Local SQLite configuration

Create private runtime directories outside the repository and the upload directory:

```sh
mkdir -p "$HOME/.local/share/ohcrud/logs" "$HOME/.local/share/ohcrud/cache"
mkdir -p public/global/files
```

Edit the existing definitions in `public/settings.php` (do not define constants twice). Replace `/absolute/private/ohcrud` below with the absolute directory you just created:

```php
define('__OHCRUD_DB_CONFIG__', [
    'DRIVER' => 'SQLITE',
    'PERSISTENT_CONNECTION' => false,
    'SQLITE_DB' => '/absolute/private/ohcrud/data.db',
    'MYSQL_HOST' => '',
    'MYSQL_DB' => '',
    'USERNAME' => '',
    'PASSWORD' => '',
]);
define('__OHCRUD_LOG_PATH__', '/absolute/private/ohcrud/logs/');
define('__OHCRUD_CACHE_PATH__', '/absolute/private/ohcrud/cache/');
```

Use trailing slashes for log/cache paths. Both the SQLite file and its parent directory must be writable by the PHP process. For local loopback HTTP only, set `__OHCRUD_SESSION_SECURE_COOKIE__` to `false`; use `true` with HTTPS. Debug mode can remain `false` throughout setup.

### MySQL or MariaDB configuration

Select `DRIVER => 'MYSQL'`, then set `MYSQL_HOST`, `MYSQL_DB`, `USERNAME`, and `PASSWORD` in the same array. Keep all array keys. The implementation constructs a host/database DSN; there are no separate port, charset, or SSL configuration keys. If your deployment needs additional connection options, change and test the connection implementation deliberately.

For Compose, use host `mysql`, database/user `ohcrud`, and the development password configured in [docker-compose.yml](../docker-compose.yml). These credentials are for local development. A host PHP process cannot resolve Compose's `mysql` service name; use the published host address or run PHP inside Compose.

## Initialize the schema

```sh
php private/migrate.php --status
php private/migrate.php
```

This creates the configured database if necessary, framework tables, migration history, and a home page. SQLite's parent directory must already exist. MySQL database creation requires additional privileges when the database is absent. No administrator is seeded. See [migrations](Migrations.md) for privileges, locking, and upgrade behavior.

## Start a local development server

For an API smoke test, PHP can route all requests through the bootstrap:

```sh
php -S 127.0.0.1:8000 -t public public/index.php
```

In another terminal:

```sh
curl -i http://127.0.0.1:8000/example/publicEndPoint/
```

Expect HTTP 200 and `Hello from ohCRUD!`. This command routes asset requests through PHP too, so it is an **API-only smoke server**, not the complete CMS development environment. Use the HTTPS Compose setup below for the themed UI, static assets, login, and uploads. The repository does not ship a PHP development-server asset router or an Apache `.htaccess` file.

## HTTPS development with Compose

1. Configure settings with the Compose MySQL values above, `/logs/` for logging, `/ramdisk/` for cache, and Secure cookies enabled. Set a unique secret.
2. Install and trust a local certificate tool such as `mkcert`, then generate the files expected by Nginx:

   ```sh
   mkcert -install
   mkcert -key-file private/key.pem -cert-file private/cert.pem ohcrud.test localhost 127.0.0.1 ::1
   ```

3. Add `127.0.0.1 ohcrud.test` to your hosts file. Ensure ports 80, 443, and 3306 are available, or adjust Compose bindings.
4. Prepare mounts and install dependencies:

   ```sh
   mkdir -p logs public/global/files
   touch data.db
   docker compose run --rm composer_installation
   docker compose up -d --build mysql php web
   docker compose logs mysql
   ```

   Wait until MariaDB is ready. `depends_on` does not establish database readiness. The `data.db` file prevents Docker from creating a directory for the SQLite bind mount, even when using MySQL. Ensure the container PHP user can write `logs/` and `public/global/files/`; set ownership for your platform instead of granting world-write permissions.

5. Provision from a one-off PHP container:

   ```sh
   docker compose run --rm -v "$PWD/private:/private:ro" \
       -e OHCRUD_PUBLIC_PATH=/app php php /private/migrate.php
   ```

6. Visit `https://ohcrud.test/`. The supplied Nginx configuration listens on HTTPS; publishing port 80 does not add an HTTP redirect. Same-origin browser calls work; add the exact HTTPS origin to the route allowlist if needed for your deployment/proxy configuration.

Stop with `docker compose down`. Do not add `-v` unless you intend to delete the named database volume. Compose is a development topology with published database ports, development credentials, and Xdebug; adapt it before production.

## Create the first administrator

Supply these variables through your shell or secret manager:

| Variable | Constraint |
| --- | --- |
| `OHCRUD_ADMIN_USERNAME` | 3–32 lowercase letters/digits |
| `OHCRUD_ADMIN_EMAIL` | Valid email address |
| `OHCRUD_ADMIN_PASSWORD` | At least 15 characters |

With the variables exported, run:

```sh
php private/create-admin.php
unset OHCRUD_ADMIN_PASSWORD
```

For Compose, the command's fixed `../public` lookup needs a different mount layout from the migration CLI:

```sh
docker compose run --rm \
    -v "$PWD/private:/workspace/private:ro" \
    -v "$PWD/public:/workspace/public:ro" \
    -e OHCRUD_ADMIN_USERNAME -e OHCRUD_ADMIN_EMAIL -e OHCRUD_ADMIN_PASSWORD \
    php php /workspace/private/create-admin.php
unset OHCRUD_ADMIN_PASSWORD
```

The container must use the MySQL configuration described above; SQLite provisioning also requires its configured file mount to be writable. The command creates a new administrator and does not reset an existing account. Sign in at `/login/`, then open `/?action=edit`. Configure an authenticator at `/security/`.

## Configuration reference

Definitions come from [settings.sample.php](../public/settings.sample.php) and [routes.php](../public/routes.php).

| Constant/group | Meaning and default |
| --- | --- |
| `__SELF__` | Absolute public directory with trailing slash |
| `__SITE__`, `__DOMAIN__`, `__SUB_DOMAIN__` | Host-derived values; the domain calculation is a simple label split, not a public-suffix parser |
| `__APP__` | Application name, also used in cache namespacing and TOTP issuer |
| `__OHCRUD_BASE_API_ROUTE__` | `/ohcrud`; coordinate changes with server rules and clients |
| `__OHCRUD_DEBUG_MODE__` | `false`; enables diagnostic output and constructor schema initialization when true |
| `__OHCRUD_DEBUG_MODE_SHOW_SQL__` | `false`; SQL display also requires debug mode |
| `__OHCRUD_DEBUG_EXPANDED_LEVEL__` | `3`; HTML debugger expansion |
| `__OHCRUD_ADMIN_ENABLE_SQL_EXECUTION__` | `false`; controls raw admin SQL execution |
| `__OHCRUD_LOG_ENABLED__`, `__OHCRUD_LOG_PATH__` | Logging enabled, default `/logs/` |
| `__OHCRUD_CACHE_ENABLED__`, `__OHCRUD_CACHE_PATH__` | Cache disabled, default `/ramdisk/` |
| `__OHCRUD_SECRET__` | Unique secret; replace the placeholder before first use |
| `__OHCRUD_SESSION_LIFETIME__` | `3600` seconds, session cookie and authentication lifetime |
| `__OHCRUD_SESSION_SECURE_COOKIE__` | `true`; requires HTTPS for normal browser session use |
| `__X_FRAME_OPTIONS__`, `__X_CONTENT_TYPE_OPTIONS__` | `SAMEORIGIN`, `nosniff` |
| `__OHCRUD_DB_STAMP__` | `true`; creation/modification timestamp and user columns |
| `__OHCRUD_DB_CONFIG__` | Single PDO connection configuration |
| `__OHCRUD_CMS_CACHE_DURATION__` | `3600` seconds for eligible Markdown fragments |
| `__OHCRUD_CMS_ADMIN_THEME__`, `__OHCRUD_CMS_ADMIN_LAYOUT__` | `admin`, `edit`; action-specific layouts can override |
| `__OHCRUD_CMS_DEFAULT_THEME__`, `__OHCRUD_CMS_DEFAULT_LAYOUT__` | `focus`, `index` |
| `__OHCRUD_CMS_LAZY_LOAD_IMAGES__` | `false`; adds lazy loading where absent |
| `__OHCRUD_CMS_MINIFY_CSS__`, `__OHCRUD_CMS_MINIFY_JS__` | `false`; generated bundles require writable `public/global/minified/` |
| `__OHCRUD_CMS_ASSET_VERSION__` | Empty release identifier; change for preserved-metadata deployments |
| `__OHCRUD_ALLOWED_IPS_ENABLED__`, `__OHCRUD_ALLOWED_IPS__` | Optional API source-IP allowlist, disabled by default |
| `__OHCRUD_ALLOWED_ORIGINS_ENABLED__`, `__OHCRUD_ALLOWED_ORIGINS__` | API origin checks, enabled by default |
| `__OHCRUD_ENDPOINTS__`, `__OHCRUD_DEFAULT_PATH_HANDLER__` | Controller map and CMS fallback |

Continue with the [quickstart](quickstart.md). For failures, use the [troubleshooting table](deployment-and-testing.md#troubleshooting).
