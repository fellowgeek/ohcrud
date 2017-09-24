<?php
    // global variables
    $GLOBALS['OHCRUD'] = true;

    // permission constant
    define('__OHCRUD_PERMISSION_ALL__', -1);

    // composer autoload
    require_once __DIR__ . '/vendor/autoload.php';

    // start PHP session
    session_set_cookie_params(__OHCRUD_SESSION_LIFETIME__, '/', __SITE__);
    session_start();
    session_write_close();

    if(PHP_SAPI == 'cli') {
        $GLOBALS['PATH_RAW'] = (isset($argv[1]) == true) ? $argv[1] : '';
    } else {
        $GLOBALS['PATH_RAW'] = $_SERVER['REQUEST_URI'];
    }

    new OhCrud\Router($GLOBALS['PATH_RAW']);
?>