<?php
namespace OhCrud;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Controller extends \OhCrud\DB {

    public function handleCORS(&$request) {

        // grant permission if script is called from command line interface
        if (PHP_SAPI == 'cli') return true;

        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
        header('Access-Control-Allow-Origin: *');
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) == true) {
            return false;
        }

        return true;
    }

}
