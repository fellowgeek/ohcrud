<?php

// Define settings for the OhCRUD application

// Define the base path for the application, normalized for both Nginx and Apache.
define('__SELF__', __DIR__ . '/');

// Define the application's URL based on the server's server name, or an empty string if not available.
define('__SITE__', $_SERVER['SERVER_NAME'] ?? '');

// Define the name of the application.
define('__APP__', 'Oh CRUD!');

// Enable or disable debug mode (set to false for production use).
define('__OHCRUD_DEBUG_MODE__', true);

// Specify the initial expanded debug levels for debugging (HTML mode only).
define('__OHCRUD_DEBUG_EXPANDED_LEVEL__', 3);

// Configure logging settings.
define('__OHCRUD_LOG_ENABLED__', true);
define('__OHCRUD_LOG_FILE__', __DIR__ . '/app.log');

// Configure caching settings.
define('__OHCRUD_CACHE_ENABLED__', false);
define('__OHCRUD_CACHE_PATH__', '/ramdisk/');

// Define the secret used for password hashing (should be unique for each application).
define('__OHCRUD_SECRET__', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

// Set the session lifetime in seconds.
define('__OHCRUD_SESSION_LIFETIME__', 3600);

// Determine whether OhCRUD should add (CDATE, MDATE, CUSER, MUSER) columns to every table.
define('__OHCRUD_DB_STAMP__', true);

// Configure the database settings, specifying the driver (can be SQLITE or MYSQL).
define('__OHCRUD_DB_CONFIG__', serialize([
        'DRIVER' => 'MYSQL',
        'PERSISTENT_CONNECTION' => false,
        'SQLITE_DB'     => __DIR__ . '/data.db',
        'MYSQL_HOST'    => 'localhost',
        'MYSQL_DB'      => 'database',
        'USERNAME'      => 'username',
        'PASSWORD'      => 'password'
        ]
    )
);

// IMPORTANT
// API end points defined in routes.php

// Define settings for the Content Management System (CMS).
define('__OHCRUD_CMS_DEFAULT_THEME__', 'default');
define('__OHCRUD_CMS_DEFAULT_LAYOUT__', 'index');
