<?php

    // OHCRUD settings

    define('__SELF__', '/' . trim(dirname(__FILE__) . '/', '/') . '/');             // path to application (normalized for nginx and apache)

	define('__SITE__', $_SERVER['HTTP_HOST']);                                      // application domain

	define('__OHCRUD_DEBUG_MODE__', true);                                          // debug mode ( set to false for production )

    define('__OHCRUD_DEBUG_EXPANDED_LEVEL__', 3);                                   // debug method initially expanded levels (for HTML mode only)

	define('__OHCRUD_LOG_FILE__', __SELF__ . 'logs/app.log');                       // debug mode ( set to false for production )

    define('__OHCRUD_SECRET__', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');        // secret string used to generate passwords ( change for each project )

    define('__OHCRUD_SESSION_LIFETIME__', 3600);                                    // session lifetime in seconds

    define('__OHCRUD_DB_STAMP__', true);                                            // should OHCRUD add (CDATE, MDATE, CUSER, MUSER) columns to every table

	define('__OHCRUD_DB_CONFIG__', serialize([                                      // database settings ( driver can be SQLITE or MYSQL )
            'DRIVER' => 'SQLITE',
            'PERSISTENT_CONNECTION' => false,                                       // should PDO use PDO::ATTR_PERSISTENT
            'SQLITE_DB' 	=> __SELF__ . 'assets/db/data.db',                      // for better security place SQLITE db outside of the web folder
            'MYSQL_HOST' 	=> 'localhost',
            'MYSQL_DB' 		=> 'database',
            'USERNAME' 		=> 'username',
            'PASSWORD' 		=> 'password'
            ]
		)
	);

	define('__OHCRUD_ENDPOINTS__', serialize([                                      // API end points
            '/example/' => 'App\Models\Example',

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

	define('__OHCRUD_DEFAULT_PATH_HANDLER__', '\App\Controllers\CMS');               // HTTP / CMS end point ( AKA default path handler )
?>