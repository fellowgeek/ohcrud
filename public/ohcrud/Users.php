<?php
namespace OhCrud;

use OTPHP\TOTP;

// Prevent direct access to this class
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Class Users - Users class for OhCrud, this class handles creation and authentication of the OhCrud framework users.
class Users extends \OhCrud\DB {

    function __construct() {
        parent::__construct();

        // Initialize the Users table and populate with default admin user if it doesn't exist
        if (__OHCRUD_DEBUG_MODE__ == true) {

            // Check if the Users table exists and create it if it doesn't
            $tableExists = false;

            switch($this->config["DRIVER"]) {
                case "SQLITE":
                    $tableExists = $this->run("SELECT * FROM sqlite_master WHERE `name`='Users';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Users` (
                            `ID` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
                            `USERNAME` TEXT,
                            `EMAIL` TEXT,
                            `HASH` TEXT,
                            `PASSWORD` TEXT,
                            `NAME` TEXT,
                            `GROUP`	INTEGER,
                            `PERMISSIONS` INTEGER,
                            `TOKEN` TEXT,
                            `ACTIVATION_HASH` TEXT,
                            `RESET_HASH` TEXT,
                            `TOTP_SECRET` TEXT,
                            `STATUS` INTEGER,
                            `TOTP` INTEGER
                        );
                    ";
                    $this->run($sql);
                    break;

                case "MYSQL":
                    $tableExists = $this->run("SELECT * FROM information_schema.tables WHERE `table_schema`='" . $this->config["MYSQL_DB"] . "' AND `table_name`= 'Users';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Users` (
                        `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
                        `USERNAME` varchar(128) NOT NULL DEFAULT '',
                        `EMAIL` varchar(128) NOT NULL DEFAULT '',
                        `HASH` varchar(128) NOT NULL DEFAULT '',
                        `PASSWORD` varchar(256) NOT NULL DEFAULT '',
                        `NAME` varchar(64) NOT NULL DEFAULT '',
                        `GROUP` int(10) unsigned NOT NULL DEFAULT '0',
                        `PERMISSIONS` int(10) unsigned NOT NULL DEFAULT '0',
                        `TOKEN` varchar(256) NOT NULL DEFAULT '',
                        `ACTIVATION_HASH` varchar(128) NOT NULL DEFAULT '',
                        `RESET_HASH` varchar(128) NOT NULL DEFAULT '',
                        `TOTP_SECRET` varchar(256) NOT NULL DEFAULT '',
                        `STATUS` int(10) unsigned NOT NULL DEFAULT '0',
                        `TOTP` int(10) unsigned NOT NULL DEFAULT '0',
                        PRIMARY KEY (`ID`),
                        UNIQUE KEY `idx_USERNAME` (`USERNAME`) USING BTREE,
                        UNIQUE KEY `idx_EMAIL` (`EMAIL`) USING BTREE,
                        UNIQUE KEY `idx_HASH` (`HASH`) USING BTREE,
                        UNIQUE KEY `idx_TOKEN` (`TOKEN`) USING BTREE,
                        UNIQUE KEY `idx_ACTIVATION_HASH` (`ACTIVATION_HASH`) USING BTREE,
                        UNIQUE KEY `idx_RESET_HASH` (`RESET_HASH`) USING BTREE,
                        KEY `idx_GROUP` (`GROUP`) USING BTREE,
                        KEY `idx_STATUS` (`STATUS`) USING BTREE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                    ";
                    $this->run($sql);
                    break;
            }

            // Create a default admin user if the Users table was just created
            if ($tableExists == false && $this->success == true) {
                $this->create('Users', [
                    'USERNAME' => 'admin',
                    'EMAIL' => 'admin@example.com',
                    'HASH' => hash('sha1', 'admin@example.com'),
                    'PASSWORD' => 'admin',
                    'NAME' => 'ohcrud admin',
                    'GROUP' => 1,
                    'PERMISSIONS' => 1,
                    'STATUS' => $this::ACTIVE,
                    'TOTP' => $this::INACTIVE
                    ]
                );
            }
        }
    }

    // Override the create function to include password hashing and token generation
    public function create($table, $data=array()) {
        // create hash from password
        if (isset($data['PASSWORD']) == true) {
            $data['PASSWORD'] = password_hash(
                $data['PASSWORD'], PASSWORD_BCRYPT, [
                    'cost' => 10
                ]
            );
        }

        // Generate an API access token based on the username
        if (isset($data['USERNAME']) == true) {
            $data['TOKEN'] = $this->encryptText($this->generateToken($data['USERNAME']));
        }

        return parent::create($table, $data);
    }

    // Override the update function to include password hashing and token generation
    public function update($table, $data, $where, $bind=array()) {

        // create hash from password
        if (isset($data['PASSWORD']) == true) {
            $data['PASSWORD'] = password_hash(
                $data['PASSWORD'], PASSWORD_BCRYPT, [
                    'cost' => 10
                ]
            );
        }

        return parent::update($table, $data, $where, $bind);
    }

    // Enable or re-generate TOTP for a user
    public function enableTOTP($id) {
        $user = $this->read(
            'Users',
            'ID = :ID AND STATUS = :STATUS',
            [
                ':ID' => $id,
                ':STATUS' => $this::ACTIVE
            ]
        )->first();

        if ($user != false) {
            $totp = TOTP::generate();

            $output = $this->Update(
                'Users',
                [
                    'TOTP' => $this::ACTIVE,
                    'TOTP_SECRET' => $this->encryptText($totp->getSecret())
                ],
                'ID = :ID',
                [
                    ':ID' => $id
                ]
            )->success;

            return $output;
        } else {
            return false;
        }
    }

    // Authenticate and check if the user has TOTP enabled
    public function login($username, $password, $token = null) {

        // Handle API token-based logins
        if (isset($token) == true) {

            // Validate the token and extract the token parts
            if (strlen($token) == 80) {
                $hash = substr($token, 0, 40);
                $APIToken = substr($token, 40, 40);
            } else {
                $this->log('warning', 'Invalid token length', [$token]);
                // Delay to mitigate brute force attacks
                sleep(1);
                return false;
            }

            // Get a user based on the token and status
            $user = $this->read(
                'Users',
                'HASH = :HASH AND STATUS = :STATUS',
                [
                    ':HASH' => $hash,
                    ':STATUS' => $this::ACTIVE
                ]
            )->first();

            // Return if user was not found
            if ($user == false) {
                $this->log('warning', 'Invalid token hash', [$token]);
                // Delay to mitigate brute force attacks
                sleep(1);
                return false;
            }

            // Decrypt and compare user token
            if ($this->decryptText($user->TOKEN) == $APIToken) {
                $user->loggedIn = true;
                // Remove sensitive information
                unset($user->PASSWORD);
                unset($user->HASH);
                unset($user->TOKEN);
                unset($user->TOTP_SECRET);
                unset($user->ACTIVATION_HASH);
                unset($user->RESET_HASH);

                // Create a user session and log in the user
                $this->setSession('User', $user);
            } else {
                $this->log('warning', 'Invalid token', [$token]);
                // Delay to mitigate brute force attacks
                sleep(1);
                return false;
            }

            return $user;
        }

        // Delay to mitigate brute force attacks, only in production mode
        if(__OHCRUD_DEBUG_MODE__ == false) {
            sleep(1);
        }

        // Get a user based on username and status
        $user = $this->read(
            'Users',
            'USERNAME = :USERNAME AND STATUS = :STATUS',
            [
                ':USERNAME' => $username,
                ':STATUS' => $this::ACTIVE
            ]
        )->first();

        if ($user != false) {
            // Verify the user's password against the stored hash
            $user->loggedIn = password_verify($password, $user->PASSWORD);
            if ($user->loggedIn == false) {
                $this->log('warning', 'Login attempt was not successful', [$username]);
                return false;
            }

            // Remove sensitive information
            unset($user->PASSWORD);
            unset($user->HASH);
            unset($user->TOKEN);
            unset($user->TOTP_SECRET);
            unset($user->ACTIVATION_HASH);
            unset($user->RESET_HASH);

            // Check if the user has TOTP enabled
            if ($user->TOTP == $this::ACTIVE) {
                $user->TOTPVerified = false;
                $this->setSession('tempUser', $user);
            }

            // Create the user session and log in the user if TOTP is not enabled for this user
            if ($user->TOTP == $this::INACTIVE) {
                $this->setSession('User', $user);
            }
        }

        return $user;
    }

    // Handle TOTP authentication for a given user ID
    public function verify($id, $TOTP_CODE) {

        // Delay to mitigate brute force attacks, only in production mode
        if(__OHCRUD_DEBUG_MODE__ == false) {
            sleep(1);
        }

        // Get the user
        $user = $this->read(
            'Users',
            'ID = :ID AND STATUS = :STATUS',
            [
                ':ID' => $id,
                ':STATUS' => $this::ACTIVE
            ]
        )->first();

        if ($user == false) {
            $this->log('warning', 'TOTP verification failed, User does not exist.', [$id]);
            return false;
        }

        // Create a TOTP object from the secret and verify the TOTP code
        $totp = TOTP::createFromSecret($this->decryptText($user->TOTP_SECRET));
        if ($totp->verify($TOTP_CODE) == false) {
            $this->log('warning', 'TOTP verification failed.', [$user->USERNAME]);
            return false;
        }

        // Remove sensitive information
        unset($user->PASSWORD);
        unset($user->HASH);
        unset($user->TOKEN);
        unset($user->TOTP_SECRET);
        unset($user->ACTIVATION_HASH);
        unset($user->RESET_HASH);

        // Create the user session and log in the user
        $this->setSession('User', $user);
        $this->unsetSession('tempUser');

        return $user;
    }

    // Handle User activation
    public function activate($username, $activationHash) {

        // Get the user
        $user = $this->read(
            'Users',
            'USERNAME = :USERNAME AND ACTIVATION_HASH = :ACTIVATION_HASH AND STATUS = :STATUS',
            [
                ':USERNAME' => $username,
                ':ACTIVATION_HASH' => $activationHash,
                ':STATUS' => $this::INACTIVE
            ],
        )->first();

        if ($user == false) {
            $this->log('warning', 'Activation attempt failed.', [$username, $activationHash]);
            return false;
        }

        // Remove sensitive information
        unset($user->PASSWORD);
        unset($user->HASH);
        unset($user->TOKEN);
        unset($user->TOTP_SECRET);
        unset($user->ACTIVATION_HASH);
        unset($user->RESET_HASH);

        // Activate the user and discard the activation hash
        $this->update(
            'Users',
            [
                'STATUS' => $this::ACTIVE,
                'ACTIVATION_HASH' => ''
            ],
            'ID = :ID',
            [
                ':ID' => $user->ID
            ]
        );

        return $user;
    }

    // Handle creating a reset hash for when user forgot their password
    public function forgot($username) {

        // Get the user
        $user = $this->read(
            'Users',
            'USERNAME = :USERNAME AND STATUS = :STATUS',
            [
                ':USERNAME' => $username,
                ':STATUS' => $this::ACTIVE
            ]
        )->first();

        if ($user == false) {
            $this->log('warning', 'Forgot password attempt failed.', [$username]);
            return false;
        }

        // Generate the password reset hash for the user
        $resetHash = hash('sha1', bin2hex(random_bytes(32)));

        // Remove sensitive information
        unset($user->PASSWORD);
        unset($user->HASH);
        unset($user->TOKEN);
        unset($user->TOTP_SECRET);
        unset($user->ACTIVATION_HASH);

        // Include the RESET_HASH hash in the response
        $user->RESET_HASH = $resetHash;

        // Update the user record
        $this->update(
            'Users',
            [
                'STATUS' => $this::ACTIVE,
                'RESET_HASH' => $resetHash
            ],
            'ID = :ID',
            [
                ':ID' => $user->ID
            ]
        );

        return $user;
    }

    // Handle resetting password
    public function reset($resetHash, $newPassword) {

        // Get the user
        $user = $this->read(
            'Users',
            'RESET_HASH = :RESET_HASH AND STATUS = :STATUS',
            [
                ':RESET_HASH' => $resetHash,
                ':STATUS' => $this::ACTIVE
            ]
        )->first();

        if ($user == false) {
            $this->log('warning', 'Reset password attempt failed.', [$resetHash]);
            return false;
        }

        // Remove sensitive information
        unset($user->PASSWORD);
        unset($user->HASH);
        unset($user->TOKEN);
        unset($user->TOTP_SECRET);
        unset($user->ACTIVATION_HASH);
        unset($user->RESET_HASH);

        // Update the user record
        $this->update(
            'Users',
            [
                'PASSWORD' => $newPassword,
                'RESET_HASH' => ''
            ],
            'ID = :ID',
            [
                ':ID' => $user->ID
            ]
        );

        return $user;
    }

    // Generate a randomized API token based on the username
    public function generateToken($username) {
        $randomString = '';
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        for($i = 0; $i < 32; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return hash('sha1', __OHCRUD_SECRET__ . (isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : PHP_SAPI) . $username . $randomString . time());
    }

}
