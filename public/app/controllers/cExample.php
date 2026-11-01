<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cExample - a sample controller provided by OhCRUD framework with a public and protected endpoint
class cExample extends \ohCRUD\DB {

    // Public endpoint: omitting methods allows every HTTP verb.
    #[\ohCRUD\Endpoint(permission: __OHCRUD_PERMISSION_ALL__)]
    public function whoami($request) {

        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        $this->data = $_SESSION['User']->USERNAME ?? 'unknown';
        $this->output();
    }

    // Protected endpoint: only administrators using GET may call this method.
    #[\ohCRUD\Endpoint(permission: 1, methods: ['GET'])]
    public function protectedEndPoint($request) {

        // Set the output type to JSON.
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Perform a read operation on the 'Users' table with a specific condition.
        $this->read(
            'Users',
            'ID = :ID',
            [
                ':ID' => 1
            ],
            'ID, USERNAME, NAME, EMAIL, STATUS',
        );

        // Output the result.
        $this->output();
    }

    // Public endpoint with an explicit HTTP method restriction.
    #[\ohCRUD\Endpoint(permission: __OHCRUD_PERMISSION_ALL__, methods: ['GET'])]
    public function publicEndPoint($request) {

        // Set the output type to HTML.
        $this->setOutputType(\ohCRUD\Core::OUTPUT_HTML);

        // Return a hello world message.
        $this->data = "Hello from ohCRUD!";

        // Output the result.
        $this->output();
    }

}
