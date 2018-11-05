<?php

// OhCRUD settings

// path to application (normalized for nginx and apache)
define('__SELF__', '/' . trim(dirname(__FILE__) . '/', '/') . '/');

// application url
define('__SITE__', $_SERVER['HTTP_HOST']);

// debug mode ( set to false for production )
define('__OHCRUD_DEBUG_MODE__', true);

// debug method initially expanded levels (for HTML mode only)
define('__OHCRUD_DEBUG_EXPANDED_LEVEL__', 3);

// log file path
define('__OHCRUD_LOG_FILE__', __SELF__ . 'logs/app.log');

// session lifetime in seconds
define('__OHCRUD_SESSION_LIFETIME__', 3600);

// should OhCRUD add (CDATE, MDATE, CUSER, MUSER) columns to every table
define('__OHCRUD_DB_STAMP__', true);

// database settings ( driver can be SQLITE or MYSQL )
define('__OHCRUD_DB_CONFIG__', serialize([
        'DRIVER' => 'MYSQL',
        'PERSISTENT_CONNECTION' => false,
        'SQLITE_DB' 	=> __SELF__ . 'assets/db/data.db',
        'MYSQL_HOST' 	=> 'localhost',
        'MYSQL_DB' 		=> 'database',
        'USERNAME' 		=> 'username',
        'PASSWORD' 		=> 'password'
        ]
    )
);

// API end points
define('__OHCRUD_ENDPOINTS__', serialize([
        '/api/pages/' => 'App\Controllers\Pages',
        '/api/users/' => 'App\Controllers\Users',
        '/api/files/' => 'App\Controllers\Files',
        '/example/' => 'App\Models\Example'

        /*
        map URL paths to objects (controllers, models, etc...)
        '/example2/' => 'App\Models\TestModel1',
        '/example1/' => 'App\Models\TestModel2',
        '/example3/' => 'App\Models\TestModel3',
        .
        .
        .
        */

        ]
    )
);

// WEB/CMS end point ( AKA default path handler )
define('__OHCRUD_DEFAULT_PATH_HANDLER__', '\App\Controllers\CMS');

?>