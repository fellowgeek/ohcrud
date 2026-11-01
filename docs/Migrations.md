# Database setup and migrations

[Documentation](DOCUMENTATION.md) · [Getting started](getting-started.md) · [Models and controllers](models-and-controllers.md) · [Deployment](deployment-and-testing.md)

Configure `public/settings.php`, install dependencies, then run from the repository root:

```sh
composer --working-dir=public install
php private/migrate.php --status
php private/migrate.php
```

The command uses the single connection in `__OHCRUD_DB_CONFIG__`. It creates a
missing SQLite database file or MySQL database, initializes `Users`, `AuthState`,
`Pages` and `Files`, and applies pending migrations from `private/migrations/`.
SQLite's parent directory must exist and be writable. Creating a MySQL database
requires `CREATE DATABASE` privileges; an existing database only needs the
privileges required by table setup and pending migrations.

Debug mode may remain disabled. Existing debug-mode table initialization still
works, but numbered migrations run only through the CLI. Existing rows are
preserved; a fresh Pages table receives its initial home page. No administrator
account is created. Provision one separately with `private/create-admin.php` and
its documented `OHCRUD_ADMIN_USERNAME`, `OHCRUD_ADMIN_EMAIL` and
`OHCRUD_ADMIN_PASSWORD` environment variables.

`--status` lists applied and pending migrations without creating databases or
tables. Exit status is zero on success and nonzero on invalid arguments,
configuration failures, changed history, lock contention or migration failures.

For the repository's Compose layout, mount the private commands into a one-off PHP
container and select its existing application mount:

```sh
docker compose run --rm -v "$PWD/private:/private:ro" \
    -e OHCRUD_PUBLIC_PATH=/app php php /private/migrate.php
```

`OHCRUD_PUBLIC_PATH` is optional and defaults to `../public` relative to the
command. The command never loads the HTTP bootstrap or routes a web request.

## Writing migrations

Use an increasing numeric prefix and a descriptive name, for example
`003-page-index.sql` or `004-content-update.php`. Versions must be unique, including
across SQL and PHP files. SQL files execute on the configured database and should
support both engines. For engine-specific operations, a PHP file returns a callable:

```php
<?php
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Add the application schema update using the supplied connection.
return function ($db, $driver) {
    // $driver is 'sqlite' or 'mysql'; $db is a PDO connection.
    $db->exec('CREATE TABLE IF NOT EXISTS Labels (ID INTEGER PRIMARY KEY)');
};
```

`Migrations` records each filename, SHA-256 checksum and completion timestamp only
after successful execution. Repeat runs skip completed migrations. Never edit or
remove an applied file; add another migration instead. The runner refuses changed
or missing history, duplicate versions, or newly introduced older migrations before
initializing tables or applying pending work.

SQLite migration bodies and their history records run in one transaction. MySQL
DDL implicitly commits, so failed migrations may leave partial schema changes;
write MySQL migrations to resume safely, inspect failures, and then rerun. Do not
commit or roll back the runner's SQLite transaction inside a PHP migration.
Base table initialization is idempotent but occurs separately from these per-file
transactions. This command applies forward migrations; it does not implement
automatic rollback or destructive reset commands.

Concurrent runs are rejected with a MySQL database advisory lock or a SQLite lock
file beside the database. Keep the SQLite `.migration.lock` file in place; removing
an active lock file would defeat coordination between processes.

Migration `002-audit-columns.php` provisions framework audit columns before normal
writes. The generic CRUD helper retains its existing handling for custom tables.

## Regression checks

```sh
python3 private/tests/migrations.py
python3 private/tests/migrations.py --mysql
```

The default test uses a disposable SQLite database. The MySQL variant uses the
running Compose MariaDB service, creates a uniquely named test database, and drops
only that database afterward. Neither test loads local application settings.

## Source and next steps

The command is [private/migrate.php](../private/migrate.php); ordering, checksums, and locks are implemented in [Migrations.php](../public/ohcrud/Migrations.php). Add application tables using the [Notes migration example](models-and-controllers.md#create-the-schema), then follow the [deployment procedure](deployment-and-testing.md#production-deployment).
