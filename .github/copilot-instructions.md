# Copilot Instructions for ohCRUD

## Project Overview
- **ohCRUD** is a PHP micro-framework for rapid API and web app development, focused on CRUD operations for MySQL/SQLite.
- Core framework files are in `public/ohcrud/` (`Core.php`, `DB.php`, `Router.php`, `Users.php`).
- Application code lives in `public/app/` (controllers, models, views).
- All classes should follow PSR-4 autoloading; Composer manages dependencies.

## Key Architectural Patterns
- **Inheritance:** All controllers/models inherit from `Core` or `DB` (which itself extends `Core`).
- **Routing:** Requests are routed via `Router`, which matches paths to classes/methods using the `__OHCRUD_ENDPOINTS__` config in `settings.php`. Unmatched paths go to `__OHCRUD_DEFAULT_PATH_HANDLER__`.
- **Permissions:** Each endpoint method can specify required permissions via a `$permissions` array property. Use `__OHCRUD_PERMISSION_ALL__` for public access.
- **Logging:** Use `$this->log()` and `$this->error()` (wrappers for Monolog) for all logging and error handling. Logs are written to `logs/app.log`.
- **Database Access:** Use `$this->run()`, `$this->create()`, `$this->read()`, `$this->update()`, `$this->delete()` for DB operations. Always use parameter binding for SQL queries.

## Developer Workflows
- **Setup:**
  - Install dependencies: `composer update && composer dump-autoload`
  - Configure webserver to route all requests to `public/index.php`.
  - Copy and edit `public/settings.sample.php` to `public/settings.php`.
- **Debugging:**
  - Enable `__OHCRUD_DEBUG_MODE__` in `settings.php` for verbose output and auto-creation of the Users table.
  - Use `$this->debug()` in any class to inspect state.
- **CLI Usage:**
  - Endpoints can be called from the command line: `php public/index.php "/endpoint/path/"`
  - CLI bypasses permissions checks.

## Project-Specific Conventions
- **Controllers:** Go in `public/app/controllers/`, namespace as `app\controllers`.
- **Models:** Go in `public/app/models/`, namespace as `app\models`.
- **Views:** Go in `public/app/views/`.
- **Assets:** Static files in `public/global/` (css, js, images, files).
- **Sensitive Files:** Keep database files and private keys outside the web root when possible.
- **Default Admin:** On first run with debug mode, default user is `admin`/`admin`.

## Integration Points
- **Monolog** for logging (see `public/ohcrud/Core.php`).
- **Composer** for dependency management and autoloading.
- **PDO** for database access (MySQL/SQLite).

## Examples
- **Controller with Permissions:**
  ```php
  namespace app\controllers;
  class MyController extends \ohCRUD\DB {
      public $permissions = [
          'object' => __OHCRUD_PERMISSION_ALL__,
          'myMethod' => 2
      ];
      // ...
  }
  ```
- **Database Query:**
  ```php
  $this->run('SELECT * FROM Users WHERE ID=:ID', [':ID' => 1]);
  ```
- **Logging:**
  ```php
  $this->log('info', 'User logged in', ['user' => $username]);
  ```
