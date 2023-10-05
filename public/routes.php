<?php

// allowed domains
define('__OHCRUD_ALLOWED_DOMAINS__', [
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
