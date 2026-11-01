<?php
    // Set a global variable for OHCRUD framework.
    $GLOBALS['OHCRUD'] = true;

    // Define a constant for permission levels, allowing all permissions.
    define('__OHCRUD_PERMISSION_ALL__', -1);

    // Include the Composer autoloader to load dependencies.
    require_once __DIR__ . '/vendor/autoload.php';

    // Refuse an unconfigured secret instead of serving an insecure installation.
    if (strlen(__OHCRUD_SECRET__) < 32 || __OHCRUD_SECRET__ === 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX') {
        http_response_code(503);
        exit('Application configuration is incomplete.');
    }

    // Register the Whoops error handling library if in debug mode.
    if (__OHCRUD_DEBUG_MODE__ === true && PHP_SAPI != 'cli') {
        $GLOBALS['coreErrorHandlersRegistered'] = true;
        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $whoops->register();
    }

    // Start a PHP session and configure session parameters.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => __OHCRUD_SESSION_LIFETIME__,
        'path' => '/',
        'secure' => __OHCRUD_SESSION_SECURE_COOKIE__,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', __OHCRUD_SESSION_LIFETIME__);
    session_start();
    session_write_close();

    // Determine the request path, either from the command line or the web server.
    if (PHP_SAPI === 'cli') {
        // If running in a command-line interface, set the path from command-line arguments.
        $GLOBALS['PATH_RAW'] = (isset($argv[1]) === true) ? $argv[1] : '';
    } else {
        // If running in a web server environment, set the path from the REQUEST_URI.
        $GLOBALS['PATH_RAW'] = $_SERVER['REQUEST_URI'];
    }

    // Create an instance of the ohCRUD\Router class to handle the request.
    new ohCRUD\Router($GLOBALS['PATH_RAW']);
