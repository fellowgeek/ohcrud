# ohCRUD Documentation

This document provides detailed documentation for the ohCRUD PHP micro framework.

## Configuration

Use the table below to define the following settings constants in the `settings.php` file that should be placed at the application's root directory. A `settings.sample.php` file is provided as a starting point. Some of these settings are defined in `routes.php`.

| Setting | Type | Description |
| --- | --- | --- |
| `__SELF__` | String | Defines the base path for the application, normalized for both Nginx and Apache. |
| `__SITE__` | String | Defines the application's URL based on the server's server name, or an empty string if not available. i.e. *example.com* |
| `__DOMAIN__` | String | Defines the domain to be used for this application (e.g., `example.com` from `www.example.com`). |
| `__SUB_DOMAIN__` | String | Defines the subdomain to be used for this application (e.g., `www` from `www.example.com`). |
| `__APP__` | String | Defines the name of the application. |
| `__OHCRUD_BASE_API_ROUTE__` | String | Defines the base API route for OhCRUD core functionality (e.g., `/ohcrud`). |
| `__OHCRUD_DEBUG_MODE__` | Boolean | If this is enabled, every ohCRUD response will contain a `runtime` and `SQL` property if applicable. This should be set to `false` for production. |
| `__OHCRUD_DEBUG_MODE_SHOW_SQL__` | Boolean | Enable SQL query in the API responses (set to `false` for production use, depends on `__OHCRUD_DEBUG_MODE__`). |
| `__OHCRUD_DEBUG_EXPANDED_LEVEL__` | Integer | This sets the initially expanded levels in the output of the `debug()` method (HTML mode only). |
| `__OHCRUD_LOG_ENABLED__` | Boolean | Enable or disable logging. |
| `__OHCRUD_LOG_PATH__` | String | Path to the default log file directory. |
| `__OHCRUD_CACHE_ENABLED__` | Boolean | Enable or disable caching. |
| `__OHCRUD_CACHE_PATH__` | String | Path to the cache directory. |
| `__OHCRUD_SECRET__` | String | Secret string used for password hashing and encryption (should be unique for each application). **Important: Change this to a random string for each project.** |
| `__OHCRUD_SESSION_LIFETIME__` | Integer | Application session cookie lifetime in seconds, default 3600 seconds. |
| `__OHCRUD_SESSION_SECURE_COOKIE__` | Boolean | Determines if the session cookie should only be sent over secure connections (HTTPS). |
| `__X_FRAME_OPTIONS__` | String | Sets the `X-Frame-Options` HTTP header to prevent clickjacking (e.g., `SAMEORIGIN`, `DENY`). |
| `__X_CONTENT_TYPE_OPTIONS__` | String | Sets the `X-Content-Type-Options` HTTP header to prevent MIME type sniffing (e.g., `nosniff`). |
| `__OHCRUD_DB_STAMP__` | Boolean | This will determine if ohCRUD should add `CDATE` (create date), `MDATE` (modified date), `CUSER` (created by user id), `MUSER` (modified by user id) columns to every table. |
| `__OHCRUD_DB_CONFIG__` | Serialize Array | Database settings for SQLITE or MYSQL should be entered here in the form of a serialized array. *If you are using a SQLITE database, it is strongly recommended that you move the database file outside of your web application directory.* |
| `__OHCRUD_ALLOWED_IPS_ENABLED__` | Boolean | Enable or disable IP filtering for allowed remote IPs. (Defined in `routes.php`). |
| `__OHCRUD_ALLOWED_IPS__` | Array | List of allowed remote IPs that can make requests to this application. (Defined in `routes.php`). |
| `__OHCRUD_ALLOWED_ORIGINS_ENABLED__`| Boolean | Enable or disable filtering for allowed origins for Cross-Origin Resource Sharing (CORS). (Defined in `routes.php`). |
| `__OHCRUD_ALLOWED_ORIGINS__` | Array | List of allowed remote origins for Cross-Origin Resource Sharing (CORS). (Defined in `routes.php`). |
| `__OHCRUD_ENDPOINTS__` | Array | API or HTML endpoints should be entered here in the form of an array. You can map any arbitrary URL path to an ohCRUD or ohCRUD compatible class to handle. (Defined in `routes.php`). |
| `__OHCRUD_DEFAULT_PATH_HANDLER__` | String | Use this setting to map any URL path that is not handled by the `__OHCRUD_ENDPOINTS__`. This needs to be an ohCRUD or ohCRUD compatible class. This is useful if you have a content management class to handle all the web pages. (Defined in `routes.php`). |
| `__OHCRUD_CMS_CACHE_DURATION__` | Integer | Define CMS cache duration in seconds (e.g., 3600 for 1 hour). |
| `__OHCRUD_CMS_ADMIN_THEME__` | String | Defines the theme to be used for the CMS admin dashboard. |
| `__OHCRUD_CMS_ADMIN_LAYOUT__` | String | Defines the default layout to be used for the CMS admin dashboard. |
| `__OHCRUD_CMS_DEFAULT_THEME__` | String | Defines the default theme for the public-facing CMS. |
| `__OHCRUD_CMS_DEFAULT_LAYOUT__` | String | Defines the default layout for the public-facing CMS. |
| `__OHCRUD_CMS_LAZY_LOAD_IMAGES__` | Boolean | Automatically adds `loading="lazy"` to all `<img>` tags in the CMS content. |
| `__OHCRUD_CMS_MINIFY_CSS__` | Boolean | Enable or disable minification of CSS files in the CMS. |
| `__OHCRUD_CMS_MINIFY_JS__` | Boolean | Enable or disable minification of JavaScript files in the CMS. |

### Routing and Security Configuration (`routes.php`)

The `public/routes.php` file contains important configuration for routing and security.

#### Endpoint Mapping

-   **`__OHCRUD_ENDPOINTS__`**: This array maps URL paths to their corresponding controller classes. The router uses this to direct incoming requests to the correct handler.
    ```php
    define('__OHCRUD_ENDPOINTS__', [
        '/api/v1/users/' => 'app\controllers\cUsers',
        // ... more endpoints
    ]);
    ```

-   **`__OHCRUD_DEFAULT_PATH_HANDLER__`**: If an incoming request does not match any path in `__OHCRUD_ENDPOINTS__`, it will be passed to the class defined here. This is typically used for a CMS or a general-purpose controller.
    ```php
    define('__OHCRUD_DEFAULT_PATH_HANDLER__', '\app\controllers\cCMS');
    ```

#### CORS (Cross-Origin Resource Sharing)

-   **`__OHCRUD_ALLOWED_ORIGINS_ENABLED__`**: A boolean to enable or disable CORS filtering. If `false`, all origins are allowed (this is not recommended for production environments).
-   **`__OHCRUD_ALLOWED_ORIGINS__`**: An array of strings, where each string is a whitelisted origin (e.g., `https://example.com`).

#### IP Filtering

-   **`__OHCRUD_ALLOWED_IPS_ENABLED__`**: A boolean to enable or disable IP address filtering.
-   **`__OHCRUD_ALLOWED_IPS__`**: An array of whitelisted IP addresses. If IP filtering is enabled, only requests from these IPs will be allowed.

## Directory Structure

ohCRUD utilizes [Composer](https://getcomposer.org/) to manage its dependencies, all classes should be [PSR-4](http://www.php-fig.org/psr/psr-4/) compliant to work with autoloading.

Here's an overview of the key folders and files in the ohCRUD project:

```
.
├── .github/                  # GitHub specific configurations (e.g., workflows, issue templates)
├── docs/                     # Project documentation, including this file
│   └── DOCUMENTATION.md      # This detailed documentation file
├── logs/                     # Default directory for application logs
├── private/                  # Private server configurations (e.g., Nginx, PHP-FPM)
├── public/                   # The web-accessible root of the application
│   ├── app/                  # Core application logic
│   │   ├── components/       # Reusable UI components for the CMS
│   │   ├── controllers/      # Handles incoming requests and orchestrates responses
│   │   ├── models/           # Manages data interactions with the database
│   │   └── views/            # Contains application-specific view templates
│   ├── global/               # Global assets (CSS, JS, images, etc.)
│   │   ├── css/              # Global CSS files
│   │   ├── images/           # Global image assets
│   │   ├── js/               # Global JavaScript files
│   │   └── minified/         # Minified CSS and JS files (if minification is enabled)
│   ├── ohcrud/               # The core ohCRUD framework files
│   │   ├── Core.php          # Base class for all framework components
│   │   ├── DB.php            # Database abstraction layer for CRUD operations
│   │   ├── Router.php        # Handles request routing and permissions
│   │   └── Users.php         # Manages user authentication and authorization
│   ├── themes/               # Directory for custom themes
│   │   ├── admin/            # Admin dashboard theme files
│   │   └── focus/            # Example public-facing theme files
│   ├── vendor/               # Composer dependencies
│   ├── composer.json         # Defines project dependencies and autoloading rules
│   ├── composer.lock         # Locks dependency versions
│   ├── index.php             # The application's entry point for all requests
│   ├── routes.php            # Defines API and web routes
│   └── settings.sample.php   # A template for the application's configuration settings
├── .gitignore                # Specifies intentionally untracked files to ignore
├── docker-compose.yml        # Docker Compose configuration for local development
├── GEMINI.md                 # Gemini CLI generated documentation
├── LICENSE                   # Project license file
└── README.md                 # Project overview and quick start guide
```

## Requirements

The ohCRUD framework has a few system requirements. You will need to make sure your server meets the following requirements:

*   PHP >= 8.0
*   PDO PHP Extension
*   Mbstring PHP Extension
*   PHP Curl Extension
*   PHP SQLITE 3 (if using SQLITE for your database)

## Docker

This project includes a `docker-compose.yml` file to make it easy to set up a local development environment. The Docker setup includes the following services:

*   **web:** An Nginx web server.
*   **php:** The PHP-FPM processor.
*   **mysql:** A MariaDB database server.
*   **composer_installation:** A one-time service to install composer dependencies.

### Prerequisites

*   [Docker](https://docs.docker.com/get-docker/)
*   [Docker Compose](https://docs.docker.com/compose/install/)

### Setup

1.  **Generate SSL Certificates:**
    The Nginx service is configured to use SSL. You'll need to generate a self-signed certificate and key. You can use OpenSSL for this. If you don't have it, you will need to install it. Run the following command from the root of the project:

    ```bash
    openssl req -x509 -newkey rsa:4096 -keyout private/key.pem -out private/cert.pem -sha256 -days 365 -nodes -subj "/C=XX/ST=State/L=City/O=Organization/OU=Department/CN=ohcrud.local"
    ```
    This will create `key.pem` and `cert.pem` in the `private` directory.

2.  **Start the services:**
    Run the following command from the root of the project to build the containers and start the services in detached mode:

    ```bash
    docker-compose up -d --build
    ```

    This will:
    *   Build the custom PHP image defined in `private/PHP.Dockerfile`.
    *   Start the Nginx, PHP, and MariaDB services.
    *   Run `composer install` to install the PHP dependencies inside a temporary container.

3.  **Access the application:**
    Once the services are running, you can access the application in your browser at `https://localhost`. You will likely see a browser warning about the self-signed SSL certificate, which you can safely ignore for local development.

### Database

The database credentials are set in the `docker-compose.yml` file:

*   **Host:** `mysql` (this is the service name within the Docker network)
*   **Database:** `ohcrud`
*   **User:** `ohcrud`
*   **Password:** `secret`
*   **Root Password:** `secret`

To connect to the database from your local machine (e.g., with a database client), you can use port `3306` (`localhost:3306`).

### Stopping the environment

To stop the services, run:

```bash
docker-compose down
```

## Webserver Configuration

### Apache

Make sure apache has the `mod_rewrite` enabled, there is a `.htaccess` file included in ohCRUD package that should take care of everything for you but if you need to make one make sure it looks like this:

```apache
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteBase /
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteRule ^.+$ index.php [QSA,L]

	RewriteRule ^/?logs/app\.log$ - [F,L]
	RewriteRule ^/?assets/db/data\.db$ - [F,L]
</IfModule>
```

Make sure your Apache virtual host is configured with the `AllowOverride All` option so that the `.htaccess` rewrite rules can be used.

### Nginx

This is an example Nginx virtual host configuration for the domain `example.com`. It listens for inbound HTTP connections on port 80.

You should update the `server_name`, `error_log`, `access_log`, and `root` directives with your own values. The `root` directive is the path to your application’s root directory.

```nginx
server {
    listen 80;
    index index.php index.html index.htm;
    error_log /path/to/example.error.log;
    access_log /path/to/example.access.log;
    root /path/to/ohcrud/public;
    server_name example.com;

    location / {
	try_files $uri $uri/ @rewrite;
    }

    location @rewrite {
        rewrite ^.+$ /index.php;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php7.0-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /(logs|assets/db) {
	deny all;
    }
}
```

## The Basics

All ohCRUD classes inherit from the `Core` class. Ideally all of your models and controllers should do the same. If a class needs database functions it should inherit from the `DB` class, which itself inherits from `Core`.

API and WEB endpoints are handled by the `Router` class. The Router checks if a user has the correct permissions to access the endpoint and routes the request to the correct class to handle.

ohCRUD uses the `Users` class to authenticate users and check permissions. When `__OHCRUD_DEBUG_MODE__` is set to `TRUE`, if the Users table does not exist, ohCRUD will auto-create the table and insert a default admin user into it.

### Default Admin Credentials

*   **Username:** admin
*   **Password:** admin

### Core Object

Every class in ohCRUD inherits from the `Core` object. This class is responsible for setting the output type and http headers and performing tasks like logging, error handling, remote requests, and debugging.

#### Properties

| Property | Type | Description |
| --- | --- | --- |
| `data` | mixed | This property holds results of a remote request or database query or any data. |
| `errors` | array | If any errors occur it will be included in this element. |
| `success` | boolean | This is set to `TRUE` if the operation was successful or `FALSE` otherwise. |
| `outputType` | string / null | Output can be set to `'HTML'`, `'JSON'` or `NULL`. |
| `outputHeaders` | array | You can set HTML headers here. |
| `outputHeadersSent` | boolean | This is set to `TRUE` if HTML headers have already been sent or `FALSE` otherwise. |
| `outputStatusCode` | integer | This is set automatically if the operation is successful or if any errors happen. You can set your own HTTP status code here as well. |
| `runtime` | float | Contains the script execution time in seconds. Only available when debug mode is on. |
| `version` | string | The version of ohCRUD. |

#### Methods

All the methods in `Core` can be used in a chain, see the example below:

```php
$this->log('info', 'This is a test log.')->debug($_SERVER)->output();
```

| Method | Description | Return Value |
| --- | --- | --- |
| `setOutputType($outputType)` | Sets the output type for the response (e.g., 'HTML', 'JSON', or null). | `Core` Object |
| `setOutputHeaders($outputHeaders = [])` | Sets custom HTTP headers for the response. | `Core` Object |
| `setOutputStatusCode($outputStatusCode)` | Sets the HTTP status code for the response. | `Core` Object |
| `setSession($key, $value)` | Sets a session variable. | `Core` Object |
| `getSession($key)` | Gets a session variable. | mixed |
| `unsetSession($key)` | Unsets a session variable. | `Core` Object |
| `regenerateSession()` | Regenerates the session ID. | `Core` Object |
| `clearSession()` | Destroys the entire session. | `Core` Object |
| `CSRF()` | Generates and returns a CSRF token. | string |
| `checkCSRF($token)` | Validates a CSRF token. | boolean |
| `output()` | Generates and sends the response based on the `outputType`. | `Core` Object |
| `headers()` | Sends the HTTP headers. | `Core` Object |
| `redirect($url)` | Performs an HTTP redirect. | boolean |
| `getCache($key, $duration = 3600)` | Retrieves data from the cache. | mixed |
| `setCache($key, $data)` | Stores data in the cache. | boolean |
| `unsetCache($key)` | Removes data from the cache. | boolean |
| `encryptText($text, $password = '')` | Encrypts a string. | string |
| `decryptText($encryptedText, $password = '')` | Decrypts a string. | string |
| `log($level, $message, ...)` | Logs a message using Monolog. | `Core` Object |
| `error($message, $outputStatusCode = 500)` | Logs an error and sets the response to an error state. | `Core` Object |
| `registerCoreErrorHandlers()` | Registers custom error and exception handlers. | void |
| `background($command, $wait = 0)` | Executes a CLI command in the background. | void |
| `console($message, $color, ...)` | Outputs a colored message to the console (CLI only). | boolean |
| `debug($expression, $label, ...)` | A wrapper for `php-ref` to debug variables. | `Core` Object |

### Database Object

The `DB` object is in charge of all the CRUD (*C*reate *R*ead *U*pdate *D*elete) work. This class inherits from `Core`, and comes with all the properties and methods that `Core` has.

#### Properties

In addition to the `Core` object properties, the `DB` object has:

| Property | Type | Description |
| --- | --- | --- |
| `lastInsertId` | integer | Stores the most recently generated auto-increment ID from a successful INSERT query. |
| `config` | array | Configuration settings for the database. |
| `db` | PDO | The PDO database connection instance. |
| `SQL` | string | Stores the last SQL query executed. |

#### Methods

Same as `Core` object plus the following:

| Method | Description | Return Value |
| --- | --- | --- |
| `run($sql, $bind=array(), ...)` | Executes any SQL query against the database. | `DB` Object |
| `create($table, $data=array())` | Inserts a new record into the database. | `DB` Object |
| `read($table, $where="", ...)` | Reads records from the database. | `DB` Object |
| `update($table, $data, $where, ...)` | Updates records in the database. | `DB` Object |
| `delete($table, $where, ...)` | Deletes records from the database. | `DB` Object |
| `first()` | Returns the first element of the `data` property. This method will terminate a method chain. | Object |
| `getPrimaryKeyColumn($table)` | Returns the primary key column name for a given table. | string / null |
| `details($table = '', ...)` | Returns the details of all tables or a specific table. | \stdClass / void |

### Router Object

The `Router` object is your interface to the outside world, it will take a `path` which can be a `URI` or a `console argument` and will call upon the appropriate class and method to handle the request.

To accomplish this task, the Router will look at the `__OHCRUD_ENDPOINTS__` constant and will call the class and method based on the defined namespace. If the path was not defined in `__OHCRUD_ENDPOINTS__`, then the Router will look at `__OHCRUD_DEFAULT_PATH_HANDLER__` and will route the request to the `defaultPathHandler` method of the class defined here.

If the Router fails to find a path after trying the steps above, it will return a 404 error.

#### Permissions

Before calling objects and methods the Router checks the target class for the public `$permissions` array property.

```php
namespace app\models;

class Example extends \ohCRUD\DB {

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'exampleMethodA' => 2,
        'exampleMethodB' => 7
    ];

    // ...
}
```

If a user is present in the `$_SESSION`, the Router will check that user's `PERMISSIONS` value against the number assigned to the target method in the `$permissions` array. If the user's `PERMISSIONS` value is *smaller than or equal to* the method's value it will grant access, otherwise it will return a 403 error.

The `'object'` key in the `$permissions` array controls the global access to the object.

To grant full access to a method or object without requiring the user to login, use the `__OHCRUD_PERMISSION_ALL__` constant.

### Users Object

The `Users` object is the ohCRUD's way of handling users and permissions. It extends the `DB` object.

#### Methods

Same as `DB` object plus the following:

| Method | Description | Return Value |
| --- | --- | --- |
| `create($table, $data=array())` | Overrides the `DB::create` method to hash passwords and generate a token. | `Users` Object |
| `update($table, $data, $where, ...)` | Overrides the `DB::update` method to hash passwords if a new password is provided. | `Users` Object |
| `enableTOTP($id, $activateTOTP = true)` | Enables or re-generates TOTP for a user. | boolean |
| `enableTOKEN($id)` | Enables or re-generates an API token for a user. | boolean |
| `login($username, $password, $token = null)` | Authenticates a user with username/password or an API token. | \stdClass / boolean |
| `verify($id, $TOTP_CODE)` | Verifies a TOTP code for a user. | \stdClass / boolean |
| `generateToken($username)` | Generates a randomized API token. | string |

## Errors & Logging

ohCRUD objects inherit from the `Core` object, which means they all have an `error($message, $outputStatusCode = 500)` and `log($level = 'debug', $message, array $context = array())` method. Use this method to throw errors and set the HTTP status code.

The `DB` object will call the `error` method when a PDO exception occurs.

ohCRUD uses the [Monolog](https://github.com/Seldaek/monolog) library to handle logs. The `log` method in `Core` is a wrapper for Monolog. Use this method to record PSR-3 compliant log messages. The `error` method will also record a log.

## Command Line Interface

You can call ohCRUD paths and endpoints from your server's command line. When calling an endpoint from the command line, ohCRUD will bypass the permissions array completely. If the output type is not defined, ohCRUD will print a debug output in the console.

```bash
php path/to/public/index.php "/endpoint/example/"
```
