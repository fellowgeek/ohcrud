<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cAdmin - admin controller used by the CMS admin interface.

class cAdmin extends \ohCRUD\DB {

    // Define permissions for the controller.
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'getTableList' => 1,
        'getTableDetails' => 1,
        'getTableData' => 1,
        'createTableRow' => 1,
        'readTableRow' => 1,
        'updateTableRow' => 1,
        'deleteTableRow' => 1,
        'createUserRow' => 1,
        'updateUserRow' => 1,
        'getUserSecrets' => 1,
        'refreshUserSecrets' => 1,
        'getLogList' => 1,
        'getLogData' => 1,
        'clearLog' => 1
    ];

    public ?array $pagination = null;

    // This function returns a list of all the tables in the database.
    public function getTableList($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        $this->data = $this->details();
        unset($this->pagination);

        $this->output();
    }

    // This function returns information about the table(s) in the database and their columns.
    public function getTableDetails($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        if (isset($request->payload) == true && isset($request->payload->TABLE) == true) {
            // Cleanup the input data
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->TABLE);
            // Get column details for the given table
            $this->data = $this->details($table, isset($request->payload->COLUMNS) ? true : false);
        } else {
            // Get column details for all tables
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
                $this->data->{$outerIndex}->COLUMNS[$innerIndex]->ICON = $this->getFAIconForDetectedType(
                    $this->data->{$outerIndex}->COLUMNS[$innerIndex]->TYPE,
                    $this->data->{$outerIndex}->COLUMNS[$innerIndex]->DETECTED_TYPE,
                    $innerValue->NAME
                );
            }
        }

        $this->output();
    }

    // This function returns the data for a given table in the database.
    public function getTableData($request) {

        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->TABLE) == true ||
            empty($request->payload->PAGE) == true ||
            empty($request->payload->LIMIT) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Cleanup the input data
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->TABLE);

        // Default values for optional parameters.
        $page = (int) $request->payload->PAGE<= 0 ? 1 : (int) $request->payload->PAGE;
        $limit = (int) $request->payload->LIMIT <= 0 ? 10 : (int) $request->payload->LIMIT;
        $order = $request->payload->ORDER ??  'DESC';
        $orderBy = $request->payload->ORDER_BY ?? $this->getPrimaryKeyColumn($table);

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
        if ($orderBy != false) {
            $SQL .= "ORDER BY " . $orderBy . " " . $order . "\n";
        }
        $SQL .= "LIMIT ". $limit . " OFFSET " . $offset . ";";
        $this->data = $this->run($SQL)->data;

        // Stop if there are errors
        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Cleanup the data and shorten long results and obfuscate ohCRUD secrets
        foreach ($this->data as $index => $value) {

            // Obfuscate secrets
            if ($table === 'Users') {
                $this->data[$index]->PASSWORD = '**********';
                $this->data[$index]->TOKEN = '**********';
                $this->data[$index]->TOTP_SECRET = '**********';
            }
            // Shorten long results
            foreach ($value as $key => $value) {
                if (gettype($value) === 'string') {
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

        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->TABLE) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success === false) {
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

        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->TABLE) == true ||
            empty($request->payload->KEY_COLUMN) == true ||
            empty($request->payload->KEY_VALUE) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success === false) {
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

        // Obfuscate secrets
        if ($table === 'Users') {
            $this->data->PASSWORD = '**********';
            $this->data->TOKEN = '**********';
            $this->data->TOTP_SECRET = '**********';
        }

        $this->output();
    }

    // This function updates a row in a given table in the database.
    public function updateTableRow($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->TABLE) == true ||
            empty($request->payload->KEY_COLUMN) == true ||
            empty($request->payload->KEY_VALUE) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success === false) {
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

    // This function creates a new user row in the database.
    public function createUserRow($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->USERNAME) == true ||
            empty($request->payload->EMAIL) == true ||
            empty($request->payload->NAME) == true ||
            empty($request->payload->GROUP) == true ||
            empty($request->payload->PERMISSIONS) == true ||
            empty($request->payload->PASSWORD) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Remove unwanted data from the payload
        unset($request->payload->CSRF);
        unset($request->payload->HASH);
        unset($request->payload->TOKEN);
        unset($request->payload->TOTP_SECRET);

        // Remove ohCRUD stamp from the payload
        unset($request->payload->CDATE);
        unset($request->payload->MDATE);
        unset($request->payload->CUSER);
        unset($request->payload->MUSER);

        // Prepare the data : USERNAME
        if (isset($request->payload->USERNAME) == true) {
            $request->payload->USERNAME = trim(strtolower($request->payload->USERNAME));
            if (strlen($request->payload->USERNAME) < 3) {
                $this->error('Username must be at least 3 characters long.');
            }
            if (strlen($request->payload->USERNAME) > 32) {
                $this->error('Username must be at most 32 characters long.');
            }
            if (preg_match('/^[a-z0-9]+$/', $request->payload->USERNAME) == false) {
                $this->error('Invalid username.');
            }
        }

        // Prepare the data : EMAIL
        if (isset($request->payload->EMAIL) == true) {
            $request->payload->EMAIL = trim(strtolower($request->payload->EMAIL));
            if (filter_var($request->payload->EMAIL, FILTER_VALIDATE_EMAIL) == true) {
                $request->payload->HASH = hash('sha1', $request->payload->EMAIL);
            } else {
                $this->error('Invalid email address.');
            }
        }
        // Prepare the data : NAME
        if (isset($request->payload->NAME) == true) {
            $request->payload->NAME = trim($request->payload->NAME);
            if (strlen($request->payload->NAME) > 32) {
                $this->error('Name must be at most 32 characters long.');
            }
            if (preg_match('/^[ a-zA-Z0-9]+$/', $request->payload->NAME) == false) {
                $this->error('Invalid name.');
            }
        }

        // Prepare the data : GROUP
        if (isset($request->payload->GROUP) == true) {
            if (is_numeric($request->payload->GROUP) == false) {
                $this->error('Invalid group.');
            } else {
                $request->payload->GROUP = (int) $request->payload->GROUP;
                if ($request->payload->GROUP  < 1) {
                    $this->error('Invalid group.');
                }
            }
        }

        // Prepare the data : PERMISSIONS
        if (isset($request->payload->PERMISSIONS) == true) {
            if (is_numeric($request->payload->PERMISSIONS) == false) {
                $this->error('Invalid permissions.');
            } else {
                $request->payload->PERMISSIONS = (int) $request->payload->PERMISSIONS;
                if ($request->payload->PERMISSIONS < 1) {
                    $this->error('Invalid permissions.');
                }
            }
        }

        // Prepare the data : STATUS
        if (isset($request->payload->STATUS) == true) {
            $request->payload->STATUS = (int) $request->payload->STATUS;
            if ($request->payload->STATUS != $this::ACTIVE && $request->payload->STATUS != $this::INACTIVE) {
                $this->error('Invalid status.');
            }
        }

        // Prepare the data : PASSWORD
        if (isset($request->payload->PASSWORD) == true && $request->payload->PASSWORD !== '**********') {
            $checkPasswordSecurity = $this->checkPasswordSecurity($request->payload->PASSWORD);
            if ($checkPasswordSecurity === 'secure') {
                $request->payload->PASSWORD = password_hash(
                    $request->payload->PASSWORD . __OHCRUD_SECRET__,
                    PASSWORD_BCRYPT,
                    [
                        'cost' => 14
                    ]
                );
            } else {
                $this->error($checkPasswordSecurity);
            }
        }

        // Prepare the data : TOTP
        if (isset($request->payload->TOTP) == true) {
            $request->payload->TOTP = (int) $request->payload->TOTP;
            if ($request->payload->TOTP != $this::ACTIVE && $request->payload->TOTP != $this::INACTIVE) {
                $this->error('Invalid TOTP status.');
            }
        }

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Update the row in the database
        $this->create(
            'Users',
            (array) $request->payload
        );

        $this->data = new \stdClass();
        $this->output();
    }

    // This function update a user row in the database.
    public function updateUserRow($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->ID) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Cleanup the input data
        $id = (int) $request->payload->ID;

        // Remove unwanted data from the payload
        unset($request->payload->CSRF);
        unset($request->payload->HASH);
        unset($request->payload->TOKEN);
        unset($request->payload->TOTP_SECRET);

        // Remove ohCRUD stamp from the payload
        unset($request->payload->CDATE);
        unset($request->payload->MDATE);
        unset($request->payload->CUSER);
        unset($request->payload->MUSER);

        // Prepare the data : USERNAME
        if (isset($request->payload->USERNAME) == true) {
            $request->payload->USERNAME = trim(strtolower($request->payload->USERNAME));
            if (strlen($request->payload->USERNAME) < 3) {
                $this->error('Username must be at least 3 characters long.');
            }
            if (strlen($request->payload->USERNAME) > 32) {
                $this->error('Username must be at most 32 characters long.');
            }
            if (preg_match('/^[a-z0-9]+$/', $request->payload->USERNAME) == false) {
                $this->error('Invalid username.');
            }
        }

        // Prepare the data : EMAIL
        if (isset($request->payload->EMAIL) == true) {
            $request->payload->EMAIL = trim(strtolower($request->payload->EMAIL));
            if (filter_var($request->payload->EMAIL, FILTER_VALIDATE_EMAIL) == true) {
                $request->payload->HASH = hash('sha1', $request->payload->EMAIL);
            } else {
                $this->error('Invalid email address.');
            }
        }

        // Prepare the data : NAME
        if (isset($request->payload->NAME) == true) {
            $request->payload->NAME = trim($request->payload->NAME);
            if (strlen($request->payload->NAME) > 32) {
                $this->error('Name must be at most 32 characters long.');
            }
            if (preg_match('/^[ a-zA-Z0-9]+$/', $request->payload->NAME) == false) {
                $this->error('Invalid name.');
            }
        }

        // Prepare the data : GROUP
        if (isset($request->payload->GROUP) == true) {
            if (is_numeric($request->payload->GROUP) == false) {
                $this->error('Invalid group.');
            } else {
                $request->payload->GROUP = (int) $request->payload->GROUP;
                if ($request->payload->GROUP  < 1) {
                    $this->error('Invalid group.');
                }
            }
        }

        // Prepare the data : PERMISSIONS
        if (isset($request->payload->PERMISSIONS) == true) {
            if (is_numeric($request->payload->PERMISSIONS) == false) {
                $this->error('Invalid permissions.');
            } else {
                $request->payload->PERMISSIONS = (int) $request->payload->PERMISSIONS;
                if ($request->payload->PERMISSIONS < 1) {
                    $this->error('Invalid permissions.');
                }
            }
        }

        // Prepare the data : STATUS
        if (isset($request->payload->STATUS) == true) {
            $request->payload->STATUS = (int) $request->payload->STATUS;
            if ($request->payload->STATUS != $this::ACTIVE && $request->payload->STATUS != $this::INACTIVE) {
                $this->error('Invalid status.');
            }
        }

        // Prepare the data : PASSWORD
        if (isset($request->payload->PASSWORD) == true && $request->payload->PASSWORD !== '**********') {
            $checkPasswordSecurity = $this->checkPasswordSecurity($request->payload->PASSWORD);
            if ($checkPasswordSecurity === 'secure') {
                $request->payload->PASSWORD = password_hash(
                    $request->payload->PASSWORD . __OHCRUD_SECRET__,
                    PASSWORD_BCRYPT,
                    [
                        'cost' => 14
                    ]
                );
            } else {
                $this->error($checkPasswordSecurity);
            }
        } else {
            unset($request->payload->PASSWORD);
        }

        // Prepare the data : TOTP
        if (isset($request->payload->TOTP) == true) {
            $request->payload->TOTP = (int) $request->payload->TOTP;
            if ($request->payload->TOTP != $this::ACTIVE && $request->payload->TOTP != $this::INACTIVE) {
                $this->error('Invalid TOTP status.');
            }
        }

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Update the row in the database
        $this->update(
            'Users',
            (array) $request->payload,
            "ID = :ID",
            [
                ':ID' => $id
            ],
        );

        $this->data = new \stdClass();
        $this->output();
    }

    // This function deletes a row from a given table in the database.
    public function deleteTableRow($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->TABLE) == true ||
            empty($request->payload->KEY_COLUMN) == true ||
            empty($request->payload->KEY_VALUE) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Cleanup the input data
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->TABLE);
        $keyColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $request->payload->KEY_COLUMN);

        // Check for super admins if we are deleting records from the Users table
        if ($table === 'Users') {
            $user = $this->read(
                $table,
                $keyColumn . " = :KEY_VALUE",
                [
                'KEY_VALUE' => $request->payload->KEY_VALUE
                ]
            )->first();

            // Check if the user is a super admin
            if ($user != false && (int) $user->STATUS === 1 && (int) $user->PERMISSIONS === 1) {

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
                if ((int) $superAdmins->COUNT === 1) {
                    $this->error('You can\'t delete the only existing superuser.');
                    $this->data = new \stdClass();
                    $this->output();
                    return $this;
                }
            }
        }

        // Delete the file if we are deleting records from the Files table
        if ($table === 'Files') {
            $file = $this->read(
                $table,
                $keyColumn . " = :KEY_VALUE",
                [
                    'KEY_VALUE' => $request->payload->KEY_VALUE
                ]
            )->first();

            if ($file != false) {
                $basePath = 'global/files/';
                $absoluteFilePath = $basePath . basename($file->PATH);
                unlink($absoluteFilePath);
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

    // This function returns the secrets for a given user.
    public function getUserSecrets($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->ID) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Cleanup the input data
        $id = (int) $request->payload->ID;

        // Read the user secrets from the database
        $user = $this->data = $this->read(
            'Users',
            'ID = :ID',
            [
                ':ID' => $id
            ],
            'USERNAME, HASH ,TOKEN, TOTP_SECRET'
        )->first();

        if ($user == false) {
            $this->error('User not found.');
            $this->output();
            return $this;
        }

        // Process HASH
        if (empty($user->HASH) == true) {
            $this->data->HASH = false;
        } else {
            $this->data->HASH = $user->HASH;
        }

        // Process TOKEN
        if (empty($user->HASH) == true || empty($user->TOKEN) == true) {
            $this->data->TOKEN = false;
        } else {
            $this->data->TOKEN = $user->HASH . $this->decryptText($user->TOKEN);
        }

        // Process TOTP_SECRET
        if (empty($user->TOTP_SECRET) == true) {
            $this->data->TOTP_SECRET = false;
            $this->data->QR_CODE = false;
        } else {
            $this->data->TOTP_SECRET = $this->decryptText($user->TOTP_SECRET);
            $this->data->QR_CODE = 'otpauth://totp/' . $user->USERNAME . '@' . __SITE__ . '?secret=' . $user->TOTP_SECRET;
        }

        $this->output();
    }

    // This function re-generates secrets for a given user.
    public function refreshUserSecrets($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->ID) == true ||
            empty($request->payload->SECRET_TYPE) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Cleanup the input data
        $id = (int) $request->payload->ID;
        $secretType = $request->payload->SECRET_TYPE ?? '';

        // Check if the secret type is valid
        if (in_array($secretType, ['TOKEN', 'TOTP_SECRET']) == false) {
            $this->error('Invalid secret type.');
            $this->output();
            return $this;
        }

        // Check if user exists
        $userExists = $this->data = $this->read(
            'Users',
            'ID = :ID',
            [
                ':ID' => $id
            ],
            'ID'
        )->first();

        if ($userExists === false) {
            $this->error('User not found.');
            $this->output();
            return $this;
        }

        // Get user model instance
        $User = new \ohCRUD\Users();

        // Re-generate the secret
        switch ($secretType) {
            case 'TOKEN':
                $User->enableTOKEN($id);
                break;

            case 'TOTP_SECRET':
                $User->enableTOTP($id, false);
                break;
        }

        $this->output();
    }

    // This function returns a list of all available log files.
    public function getLogList($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();
        $result = [];

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Get a list of log files in the '__OHCRUD_LOG_PATH__' directory
        $scan = glob(__OHCRUD_LOG_PATH__ . '*.log');

        // Iterate through the list of HTML files
        foreach ($scan as $logFile) {
            $result[] = [
                'NAME' => basename($logFile),
                'PATH' => __OHCRUD_LOG_PATH__ . basename($logFile)
            ];
        }
        $this->data = $result;
        unset($this->pagination);

        $this->output();
    }

    public function getLogData($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();
        $result = [];

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->LOG) == true ||
            empty($request->payload->PAGE) == true ||
            empty($request->payload->LIMIT) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Sanitize and validate file name
        $filename = basename($request->payload->LOG); // removes any path parts
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            $this->error('Invalid log filename.');
            $this->output();
            return $this;
        }

        $filepath = realpath(__OHCRUD_LOG_PATH__ . $filename);

        // Ensure the resolved path is inside the log directory
        if (strpos($filepath, realpath(__OHCRUD_LOG_PATH__)) !== 0 || !file_exists($filepath)) {
            $this->error('Log file not found or invalid path.');
            $this->output();
            return $this;
        }

        // Default values for optional parameters.
        $page = (int) $request->payload->PAGE <= 0 ? 1 : (int) $request->payload->PAGE;
        $limit = (int) $request->payload->LIMIT <= 0 ? 10 : (int) $request->payload->LIMIT;

        // Get total records
        $totalRecords = $this->countLogRecords($filename);
        if ($totalRecords === false) {
            $this->error('Log file not found.');
            $this->output();
            return $this;
        }

        // Calculate total pages
        $totalPages = ceil($totalRecords / $limit);

        // Clamp the variable ranges
        if ($limit > 100) $limit = 100;
        if ($limit > $totalRecords) $limit = $totalRecords;
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $limit;
        if ($offset < 0) $offset = 0;

        // Open log file
        $fp = fopen($filepath, 'r');
        if (!$fp) {
            $this->error('Unable to open log file: ' . $filename);
            $this->output();
            return $this;
        }

        $bufferSize = 4096;
        $pos = 0;
        $lines = [];
        $currentLine = '';
        $foundLines = 0;

        fseek($fp, 0, SEEK_END);
        $fileSize = ftell($fp);

        while ($fileSize + $pos > 0 && $foundLines < ($limit + $offset)) {
            $seekSize = min($bufferSize, $fileSize + $pos);
            $pos -= $seekSize;
            fseek($fp, $pos, SEEK_END);
            $chunk = fread($fp, $seekSize);

            $currentLine = $chunk . $currentLine;

            $linesInChunk = explode("\n", $currentLine);
            // Last (incomplete) line to keep for next iteration
            $currentLine = array_shift($linesInChunk);

            foreach (array_reverse($linesInChunk) as $line) {
                $trimmed = trim($line);
                $trimmed = str_replace('"file":"' . __SELF__, '"file":".../', $trimmed);
                if ($trimmed !== '') {
                    $lines[] = $trimmed;
                    $foundLines++;
                    if ($foundLines >= ($limit + $offset)) {
                        break;
                    }
                }
            }
        }

        if ($foundLines < ($limit + $offset) && !empty($currentLine)) {
            $remainingLines = explode("\n", $currentLine);
            foreach (array_reverse($remainingLines) as $line) {
                $trimmed = trim($line);
                $trimmed = str_replace('"file":"' . __SELF__, '"file":".../', $trimmed);
                if ($trimmed !== '') {
                    $lines[] = $trimmed;
                    $foundLines++;
                    if ($foundLines >= ($limit + $offset)) {
                        break;
                    }
                }
            }
        }

        fclose($fp);

        // Apply offset and limit, and parse JSON
        $result = array_slice($lines, $offset, $limit);
        $this->data = array_values(array_filter(array_map('json_decode', $result)));

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

    public function clearLog($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables
        $this->data = new \stdClass();

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->LOG) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Sanitize and validate file name
        $filename = basename($request->payload->LOG); // removes any path parts
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            $this->error('Invalid log filename.');
            $this->output();
            return $this;
        }

        $filepath = realpath(__OHCRUD_LOG_PATH__ . $filename);

        // Ensure the resolved path is inside the log directory
        if (strpos($filepath, realpath(__OHCRUD_LOG_PATH__)) !== 0 || !file_exists($filepath)) {
            $this->error('Log file not found or invalid path.');
            $this->output();
            return $this;
        }

        // Clear the log file
        file_put_contents($filepath, '');
        $this->success = true;

        $this->output();
    }

    // This function returns a font-awesome icon based on a given data type.
    private function getFAIconForDetectedType($type, $detectedType, $columnName = '') {

        // Handle icons for ohCRUD stamp fields
        if (in_array($columnName, ['CDATE', 'MDATE']) == true) {
            return 'fa-calendar';
        }
        if (in_array($columnName, ['CUSER', 'MUSER']) == true) {
            return 'fa-hashtag';
        }

        // Handle icons for ohCRUD columns
        if (in_array($columnName, ['STATUS', 'TOTP']) == true) {
            return 'fa-toggle-on';
        }

        // Get icons based on type
        switch ($type) {
            case 'tinyint(1)':
            case 'boolean':
            case 'bool':
            case 'bit(1)':
                return 'fa-toggle-on';
        }

        // Get icons based on detected type
        switch ($detectedType) {
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

    // Checks if a given password is secure based on basic criteria.
    private function checkPasswordSecurity($password) {
        // Define minimum length for the password
        $minLength = 8;

        // Initialize an array to hold the security status and message
        $output = 'secure';

        // 1. Check for minimum length
        if (strlen($password) < $minLength) {
            $output = "Password is too short. It must be at least {$minLength} characters long.";
            return $output;
        }

        // 2. Check for presence of different character types (example: uppercase, lowercase, number, special character)
        if (!preg_match('/[A-Z]/', $password)) {
            $output = 'Password must contain at least one uppercase letter.';
            return $output;
        }

        if (!preg_match('/[a-z]/', $password)) {
            $output = 'Password must contain at least one lowercase letter.';
            return $output;
        }

        if (!preg_match('/[0-9]/', $password)) {
            $output = 'Password must contain at least one number.';
            return $output;
        }

        // If all checks pass
        return $output;
    }

    // This function counts the number of log records in a given log file.
    private function countLogRecords($logFile)  {

        if (file_exists(__OHCRUD_LOG_PATH__ . $logFile) == false) {
            return false;
        }

        $fp = fopen(__OHCRUD_LOG_PATH__ . $logFile, 'r');
        if (!$fp) {
            return false;
        }

       // A simple way to count lines
        $lineCount = 0;
        $handle = fopen(__OHCRUD_LOG_PATH__ . $logFile, "r");
        while(!feof($handle)) {
            $line = fgets($handle);
            if (trim($line) !== '') {
                $lineCount++;
            }
        }
        fclose($handle);
        return $lineCount;
    }

}