<?php

// OhCRUD settings

// path to application (normalized for nginx and apache)
define('__SELF__', __DIR__ . '/');

// application url
define('__SITE__', $_SERVER['SERVER_NAME'] ?? '');

// debug mode ( set to false for production )
define('__OHCRUD_DEBUG_MODE__', true);

// debug method initially expanded levels (for HTML mode only)
define('__OHCRUD_DEBUG_EXPANDED_LEVEL__', 3);

// logs
define('__OHCRUD_LOG_ENABLED__', true);
define('__OHCRUD_LOG_FILE__', __DIR__ . '/app.log');

// cache settings
define('__OHCRUD_CACHE_ENABLED__', false);
define('__OHCRUD_CACHE_PATH__', '/ramdisk/');

// secret used for password hash ( should be unique for each application )
define('__OHCRUD_SECRET__', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

// session lifetime in seconds
define('__OHCRUD_SESSION_LIFETIME__', 3600);

// should OhCRUD add (CDATE, MDATE, CUSER, MUSER) columns to every table
define('__OHCRUD_DB_STAMP__', true);

// database settings ( driver can be SQLITE or MYSQL )
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

// API end points
define('__OHCRUD_ENDPOINTS__', serialize([
        '/api/pages/' => 'app\controllers\cPages',
        '/api/users/' => 'app\controllers\cUsers',
        '/api/files/' => 'app\controllers\cFiles',
        '/example/' => 'app\controllers\cExample'
        /*
        map URL paths to objects (controllers, models, etc...)
        '/example1/' => 'app\models\mTestModel',
        '/example2/' => 'app\controllers\cTestController',
        .
        .
        .
        */
        ]
    )
);

// WEB/CMS end point ( AKA default path handler )
define('__OHCRUD_DEFAULT_PATH_HANDLER__', '\app\controllers\cCMS');

// CMS settings
define('__OHCRUD_CMS_DEFAULT_THEME__', 'default');
define('__OHCRUD_CMS_DEFAULT_LAYOUT__', 'index');
