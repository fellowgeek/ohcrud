<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cAdmin - admin controller used by the CMS admin interface.
class cAdmin extends \OhCrud\DB {

    // Define permissions for the controller.
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'getDatabaseDetails' => 1,
    ];

    // This function returns information about the database and its tables.
    public function getDatabaseDetails($request) {
        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = [];

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        if (isset($request->payload) == true && isset($request->payload->TABLE) == true) {
            $this->data = $this->details($request->payload->TABLE, isset($request->payload->COLUMNS) ? true : false);
        } else {
            $this->data = $this->details('', isset($request->payload->COLUMNS) ? true : false);
        }

        $this->output();
    }

}
