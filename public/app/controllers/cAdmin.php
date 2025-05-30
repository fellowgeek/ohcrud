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
        'createTableRow' => 1,
        'readTableRow' => 1,
        'updateTableRow' => 1,
        'deleteTableRow' => 1
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

        // Update the COLUMNS details and add ICON based on detected data types.
        foreach($this->data as $outerIndex => $outerValue) {
            if (isset($outerValue->COLUMNS) == false) {
                continue;
            }

            $columns = $this->data->{$outerIndex}->COLUMNS;
            foreach ($columns as $innerIndex => $innerValue) {
                $this->data->{$outerIndex}->COLUMNS[$innerIndex]->ICON = $this->getFAIconForDetectedType($this->data->{$outerIndex}->COLUMNS[$innerIndex]->DETECTED_TYPE, $innerValue->NAME);
            }
        }

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

        // Cleanup the input data
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->TABLE);

        // Default values for optional parameters.
        $page = (int) $request->payload->PAGE<= 0 ? 1 : (int) $request->payload->PAGE;
        $limit = (int) $request->payload->LIMIT <= 0 ? 10 : (int) $request->payload->LIMIT;
        $order = $request->payload->ORDER ??  'DESC';
        $orderBy = $request->payload->ORDER_BY ?? NULL;

        // Get total records
        $totalRecords = $this->RUN("SELECT COUNT(*) AS `COUNT` FROM " . $table, [], false)->first()->COUNT;
        $totalPages = ceil($totalRecords / $limit);

        // Clamp the variable ranges
        if ($limit > 100) $limit = 100;
        if ($limit > $totalRecords) $limit = $totalRecords;
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $limit;
        if ($offset < 0) $offset = 0;

        // Build the SQL query
        $SQL = "SELECT * FROM " . $table . "\n";
        if ($orderBy != NULL) {
            $SQL .= "ORDER BY " . $orderBy . " " . $order . "\n";
        }
        $SQL .= "LIMIT ". $limit . " OFFSET " . $offset . ";";
        $this->data = $this->run($SQL)->data;

        // Cleanup the data and shorten long results
        foreach ($this->data as $index => $value) {
            foreach ($value as $key => $value) {
                if (gettype($value) == 'string') {
                    $this->data[$index]->{$key} = $this->shortenString($value, 100);
                }
            }
        }

        // Get pagination meta data

        $hasNextPage = $page < $totalPages;
        $hasPreviousPage = $page > 1;
        $showingRangeFrom = ($offset + 1);
        $showingRangeTo = ($offset + $limit);
        if ($showingRangeTo > $totalRecords) $showingRangeTo = $totalRecords;

        $showing = $showingRangeFrom . ' - ' . $showingRangeTo . ' of ' . $totalRecords;

        $this->pagination = [
            'totalRecords' => $totalRecords,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'limit' => $limit,
            'hasNextPage' => $hasNextPage,
            'hasPreviousPage' => $hasPreviousPage,
            'showing' => $showing
        ];

        $this->output();
    }

    // This function inserts a new row into a given table in the database.
    public function createTableRow($request) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->TABLE) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        // Cleanup the input data
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->TABLE);

        // Remove unwanted data from the payload
        unset($request->payload->CSRF);
        unset($request->payload->TABLE);

        // Remove ohCRUD stamp from the payload
        unset($request->payload->CDATE);
        unset($request->payload->MDATE);
        unset($request->payload->CUSER);
        unset($request->payload->MUSER);

        // Update the row in the database
        $this->create(
            $table,
            (array) $request->payload
        );

        $this->data = new \stdClass();
        $this->output();
    }

    // This function returns the data for a given table row in the database.
    public function readTableRow($request) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->TABLE) == true ||
            empty($request->payload->KEY_COLUMN) == true ||
            empty($request->payload->KEY_VALUE) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        // Cleanup the input data
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->TABLE);
        $keyColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->KEY_COLUMN);

        // Read the row from the database
        $this->data = $this->read(
            $table,
            $keyColumn . " = :KEY_VALUE",
            [
                'KEY_VALUE' => $request->payload->KEY_VALUE
            ]
        )->first();

        $this->output();
    }

    // This function updates a row in a given table in the database.
    public function updateTableRow($request) {
        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->TABLE) == true ||
            empty($request->payload->KEY_COLUMN) == true ||
            empty($request->payload->KEY_VALUE) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        // Cleanup the input data
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->TABLE);
        $keyColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->KEY_COLUMN);
        $keyValue = $request->payload->KEY_VALUE;

        // Remove unwanted data from the payload
        unset($request->payload->CSRF);
        unset($request->payload->TABLE);
        unset($request->payload->KEY_COLUMN);
        unset($request->payload->{$keyColumn});
        unset($request->payload->KEY_VALUE);

        // Remove ohCRUD stamp from the payload
        unset($request->payload->CDATE);
        unset($request->payload->MDATE);
        unset($request->payload->CUSER);
        unset($request->payload->MUSER);

        // Update the row in the database
        $this->update(
            $table,
            (array) $request->payload,
            $keyColumn . " = :KEY_VALUE",
            [
                'KEY_VALUE' => $keyValue
            ],
        );

        $this->data = new \stdClass();
        $this->output();
    }

    // This function deletes a row from a given table in the database.
    public function deleteTableRow($request) {
        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->TABLE) == true ||
            empty($request->payload->KEY_COLUMN) == true ||
            empty($request->payload->KEY_VALUE) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        // Cleanup the input data
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->TABLE);
        $keyColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->KEY_COLUMN);

        // Check for super admins if we are deleting records from the Users table
        if ($table == 'Users') {
            $user = $this->read(
                $table,
                $keyColumn . " = :KEY_VALUE",
                [
                'KEY_VALUE' => $request->payload->KEY_VALUE
                ]
            )->first();

            // Check if the user is a super admin
            if ($user != false && $user->STATUS == 1 && $user->PERMISSIONS == 1) {

                // Get the number of super admins
                $superAdmins = $this->run(
                    "SELECT
                        COUNT(ID) AS `COUNT`
                    FROM
                        `Users`
                    WHERE
                        `PERMISSIONS` = 1 AND
                        `STATUS` = 1
                    "
                )->first();

                // Check if this is the last super admin
                if ($superAdmins->COUNT == 1) {
                    $this->error('You can\'t delete the only existing superuser.');
                    $this->data = new \stdClass();
                    $this->output();
                    return $this;
                }
            }
        }

        // Delete the row from the database
        $this->delete(
            $table,
            $keyColumn . " = :KEY_VALUE",
            [
                'KEY_VALUE' => $request->payload->KEY_VALUE
            ]
        );

        $this->data = new \stdClass();
        $this->output();
    }

    // This function returns a font-awesome icon based on a given data type.
    private function getFAIconForDetectedType($type, $columnName = '') {

        // Handle icons for ohCRUD stamp fields
        if (in_array($columnName, ['CDATE', 'MDATE']) == true) {
            return 'fa-calendar';
        }
        if (in_array($columnName, ['CUSER', 'MUSER']) == true) {
            return 'fa-hashtag';
        }

        switch ($type) {
            case 'empty':
                return 'fa-square-o';
            case 'email':
                return 'fa-envelope-o';
            case 'URL':
                return 'fa-link';
            case 'IPv4':
                return 'fa-globe';
            case 'IPv6':
                return 'fa-globe';
            case 'datetime':
                return 'fa-calendar';
            case 'date':
                return 'fa-calendar';
            case 'time':
                return 'fa-clock-o';
            case 'UUID':
                return 'fa-star-o';
            case 'boolean-like':
                return 'fa-toggle-on';
            case 'integer':
                return 'fa-hashtag';
            case 'float':
                return 'fa-hashtag';
            case 'encrypted (bcrypt)':
                return 'fa-shield';
            case 'hash (MD5)':
                return 'fa-snowflake-o';
            case 'hash (SHA1)':
                return 'fa-snowflake-o';
            case 'hash (SHA256)':
                return 'fa-snowflake-o';
            case 'hash (unkown)':
                return 'fa-snowflake-o';
            case 'base64':
                return 'fa-snowflake-o';
            case 'encrypted (guessed)':
                return 'fa-shield';
            case 'string':
                return 'fa-font';
            default:
                return 'fa-square-o';
        }
    }

    // This function shortens a given string to a specified length.
    private function shortenString(string $string, int $maxLength) {
        // Check if the string is already shorter than or equal to the max length
        if (strlen($string) <= $maxLength) {
            return $string;
        }

        $truncatedLength = $maxLength - 3;
        if ($truncatedLength < 0) {
            $truncatedLength = 0; // Or handle as an error, depending on desired behavior
        }

        return substr($string, 0, $truncatedLength) . '...';
    }

}