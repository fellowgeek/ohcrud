<?php
namespace OhCrud;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Controller extends \OhCrud\DB {

    public function handleCORS(&$request) {

        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
        header('Access-Control-Allow-Origin: *');
        if (isset($request->TOKEN) == false) {
            return (__OHCRUD_DEBUG_MODE__ == false ? false : true);
        } else if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) == true) { return false; }

        // search user token
        $tokenExists = $this->read(
            'Users',
            'TOKEN = :TOKEN',
            [
                ':TOKEN' => $request->TOKEN
            ]
        )->first();
        if ($tokenExists == false) { return false; }

        unset($request->TOKEN);
        unset($request->GET->TOKEN);
        unset($request->POST->TOKEN);
        return true;

    }

}
