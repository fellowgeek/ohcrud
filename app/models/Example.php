<?php
namespace App\Models;

// prevent direct access
if(isset($GLOBALS['OHCRUD']) == false) { die(); }

class Example extends \OhCrud\DB {

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'protectedEndPoint' => 1,
        'publicEndPoint' => __OHCRUD_PERMISSION_ALL__
    ];

    public function protectedEndPoint($request) {

        $this->setOutputType('JSON');

        $this->read(
            'Users',
            'ID=:ID',
            [
                ':ID' => 1
            ],
            'ID, USERNAME, FIRSTNAME, LASTNAME'
        );

    }

    public function publicEndPoint($request) {

        $this->setOutputType('HTML');
        $this->debug($request);

    }

}
