<?php
namespace app\controllers;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class cExample extends \OhCrud\DB {

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'protectedEndPoint' => 1,
        'publicEndPoint' => __OHCRUD_PERMISSION_ALL__
    ];

    public function protectedEndPoint($request) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        $this->read(
            'Users',
            'ID=:ID',
            [
                ':ID' => 1
            ],
            'ID, USERNAME, FIRSTNAME, LASTNAME, TOTP, STATUS'
        
        );

        $this->output();

    }

    public function publicEndPoint($request) {

        // $this->setOutputType(\OhCrud\Core::OUTPUT_HTML);
        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        $this->data = new \stdClass();
        $this->data->session = $_SESSION;
        $this->data->server = $_SERVER;
        $this->data->headers = getallheaders();
        $this->output();

        // $this->debug($_SESSION, 'Session:');
        // $this->debug($_SERVER, 'Server Information:');
        // $this->debug(getallheaders(), 'HTTP Headers:');
        
    }

}
