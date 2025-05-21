<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cAdmin - admin controller used by the CMS admin interface.

class cAdmin extends \OhCrud\DB {

    // Define permissions for the controller.
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'getTableList' => 1,
        'getTableDetails' => 1,
        'getTableData' => 1,
    ];

    public ?array $pagination = null;

    // This function returns a list of all the tables in the database.
    public function getTableList($request) {
        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        $this->data = $this->details();
        unset($this->pagination);

        $this->output();
    }

    // This function returns information about the table(s) in the database and their columns.
    public function getTableDetails($request) {
        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

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
        unset($this->pagination);

        $this->output();
    }

    // This function returns the data for a given table in the database.
    public function getTableData($request) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->TABLE) == true ||
            empty($request->payload->PAGE) == true ||
            empty($request->payload->LIMIT) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        // Check if the requested table is restricted.
        if ($request->payload->TABLE == 'Users') {
            $this->error('Restricted table.');
            $this->output();
            return $this;
        }

        // Default values for optional parameters.
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->TABLE);
        $page = (int) $request->payload->PAGE<= 0 ? 1 : (int) $request->payload->PAGE;
        $limit = (int) $request->payload->LIMIT <= 0 ? 10 : (int) $request->payload->LIMIT;
        $order = $request->payload->ORDER ??  'DESC';
        $orderBy = $request->payload->ORDER_BY ?? NULL;
        $offset = ($page - 1) * $limit;

        // Get total records
        $totalRecords = $this->RUN("SELECT COUNT(*) AS `COUNT` FROM " . $table, [], false)->first()->COUNT;

        // Build the SQL query
        $SQL = "SELECT * FROM " . $table . "\n";
        if ($orderBy != NULL) {
            $SQL .= "ORDER BY " . $orderBy . " " . $order . "\n";
        }
        $SQL .= "LIMIT ". $limit . " OFFSET " . $offset . ";";
        $this->data = $this->run($SQL)->data;

        // Get pagination meta data
        $totalPages = ceil($totalRecords / $limit);
        $hasNextPage = $page < $totalPages;
        $hasPreviousPage = $page > 1;

        $this->pagination = [
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'limit' => $limit,
            'hasNextPage' => $hasNextPage,
            'hasPreviousPage' => $hasPreviousPage
        ];

        $this->output();
    }

}