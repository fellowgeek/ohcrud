<?php

// allowed remote IPs ( IP filtering )
define('__OHCRUD_ALLOWED_IPS_ENABLED__', false);

// list of allowed remote IPs that can make request to this application
define('__OHCRUD_ALLOWED_IPS__', [
        '127.0.0.1',
    ]
);

// list of allowed remote origins for CORS
define('__OHCRUD_ALLOWED_ORIGINS__', [
        'http://localhost',
        'http://ohcrud.local',
    ]
);

// API end points
define('__OHCRUD_ENDPOINTS__', [
    '/api/themes/' => 'app\controllers\cThemes',
    '/api/pages/' => 'app\controllers\cPages',
    '/api/users/' => 'app\controllers\cUsers',
    '/api/files/' => 'app\controllers\cFiles',
    '/example/' => 'app\controllers\cExample'

    /*
    map URL paths to objects (controllers, models, etc...)
    '/example1/' => 'app\controllers\cSandbox',
    */
    ]
);

// WEB/CMS end point ( AKA default path handler )
define('__OHCRUD_DEFAULT_PATH_HANDLER__', '\app\controllers\cCMS');
