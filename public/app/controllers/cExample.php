<?php
namespace app\controllers;

use OTPHP\TOTP;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cExample - a sample controller provided by OhCRUD framework with a public and protected endpoint
class cExample extends \OhCrud\DB {

    // Define permissions for the controller.
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'whoami' => __OHCRUD_PERMISSION_ALL__,
        'protectedEndPoint' => 1,
        'publicEndPoint' => __OHCRUD_PERMISSION_ALL__
    ];

    // This function returns information about the current user of the API
    public function whoami($request) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        $this->data = $_SESSION['User']->USERNAME ?? 'unknown';
        $this->output();
    }

    // This function handles the protected endpoint.
    public function protectedEndPoint($request) {

        // Set the output type to JSON.
        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // Perform a read operation on the 'Users' table with a specific condition.
        $this->update(
            'Users',
            [
                'NAME' => 'test'
            ],
            'ID=:ID',
            [
                ':ID' => 1
            ]

        );

        // Output the result.
        $this->output();

    }

    // This function handles the public endpoint.
    public function publicEndPoint($request) {

        // Set the output type to HTML.
        $this->setOutputType(\OhCrud\Core::OUTPUT_HTML);

        // Return a hello world message.
        // $this->data = "Hello from ohCRUD!";

        $response = $this->details('', true);
        $this->debug($response);

        // Output the result.
        $this->output();

    }

}
