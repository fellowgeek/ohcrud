<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cExample - a sample controller provided by OhCRUD framework with a public and protected endpoint
class cExample extends \OhCrud\DB {

    // Define permissions for the controller.
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'protectedEndPoint' => 1,
        'publicEndPoint' => __OHCRUD_PERMISSION_ALL__
    ];

    // This function handles the protected endpoint.
    public function protectedEndPoint($request) {

        // Set the output type to JSON.
        // $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // Perform a read operation on the 'Users' table with a specific condition.
        $this->read(
            'Users',
            'ID=:ID',
            [
                ':ID' => 1
            ],
            'ID, USERNAME, FIRSTNAME, LASTNAME, TOTP, STATUS'

        );

        $this->debug($_SERVER);
        $this->debug($this->data);

        // Output the result.
        $this->output();

    }

    // This function handles the public endpoint.
    public function publicEndPoint($request) {

        // Set the output type to HTML.
        $this->setOutputType(\OhCrud\Core::OUTPUT_HTML);

        // Debug the session data and display it.
        $this->debug($_SESSION, 'Session:');

        // Output the result.
        $this->output();

    }

}
