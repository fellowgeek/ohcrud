<?php
namespace app\controllers;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class cExample extends \OhCrud\Controller {

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
            'ID, USERNAME, FIRSTNAME, LASTNAME'
        );

        $this->output();
        // $this->debug($_SESSION);
        // $this->debug();

    }

    public function publicEndPoint($request) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_HTML);
        $this->debug($request);

        $this->create('Users', [
            'USERNAME' => 'admin',
            'PASSWORD' => password_hash(
                'admin', PASSWORD_BCRYPT, [
                    'cost' => 10
                    ]
                ),
            'FIRSTNAME' => 'admin',
            'LASTNAME' => 'admin',
            'GROUP' => 1,
            'PERMISSIONS' => 1,
            // 'TOKEN' => $this->generateToken('admin'),
            'STATUS' => 1
            ]
        );


    }

}
