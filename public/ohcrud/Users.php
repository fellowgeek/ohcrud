<?php
namespace OhCrud;

use OTPHP\TOTP;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Users extends \OhCrud\DB {

    function __construct() {
        parent::__construct();

        if (__OHCRUD_DEBUG_MODE__ == true) {

            // variables
            $tableExists = false;

            switch($this->config["DRIVER"]) {
                case "SQLITE":
                    $tableExists = $this->run("SELECT * FROM sqlite_master WHERE `name`='Users';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Users` (
                            `ID`	INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
                            `USERNAME`	TEXT,
                            `EMAIL`	TEXT,
                            `PASSWORD`	TEXT,
                            `FIRSTNAME`	TEXT,
                            `LASTNAME`	TEXT,
                            `GROUP`	INTEGER,
                            `PERMISSIONS`	INTEGER,
                            `TOKEN`	TEXT,
                            `TOTP_SECRET`	TEXT,
                            `STATUS`	INTEGER,
                            `TOTP`	INTEGER,
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
                        `PASSWORD` varchar(256) NOT NULL DEFAULT '',
                        `FIRSTNAME` varchar(64) NOT NULL DEFAULT '',
                        `LASTNAME` varchar(64) NOT NULL DEFAULT '',
                        `GROUP` int(10) unsigned NOT NULL DEFAULT '0',
                        `PERMISSIONS` int(10) unsigned NOT NULL DEFAULT '0',
                        `TOKEN` varchar(256) NOT NULL DEFAULT '',
                        `TOTP_SECRET` varchar(256) NOT NULL DEFAULT '',
                        `STATUS` int(10) unsigned NOT NULL DEFAULT '0',
                        `TOTP` int(10) unsigned NOT NULL DEFAULT '0',
                        PRIMARY KEY (`ID`),
                        KEY `idx_USERNAME` (`USERNAME`) USING BTREE,
                        KEY `idx_EMAIL` (`EMAIL`) USING BTREE,
                        KEY `idx_TOKEN` (`TOKEN`) USING BTREE,
                        KEY `idx_GROUP` (`GROUP`) USING BTREE,
                        KEY `idx_STATUS` (`STATUS`) USING BTREE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                    ";
                    $this->run($sql);
                    break;
            }

            if ($tableExists == false && $this->success == true) {
                $this->create('Users', [
                    'USERNAME' => 'admin',
                    'EMAIL' => 'admin@example.com',
                    'PASSWORD' => 'admin',
                    'FIRSTNAME' => 'admin',
                    'LASTNAME' => 'admin',
                    'GROUP' => 1,
                    'PERMISSIONS' => 1,
                    'STATUS' => $this::ACTIVE,
                    'TOTP' => $this::INACTIVE
                    ]
                );
            }
        }
    }

    // overwrite create function to include TOKEN and PASSWORD
    public function create($table, $data=array()) {
        // create hash from password
        if (isset($data['PASSWORD']) == true) {
            $data['PASSWORD'] = password_hash(
                $data['PASSWORD'], PASSWORD_BCRYPT, [
                    'cost' => 10
                ]
            );
        }

        // set API access token
        if (isset($data['USERNAME']) == true) {
            $data['TOKEN'] = $this->generateToken($data['USERNAME']);
        }

        return parent::create($table, $data);
    }

    // enable/re-generate TOTP login
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
                    'TOTP_SECRET' => $totp->getSecret()
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

    // method to provide authentication and check if user has TOTP enabled
    public function login($username, $password, $token = null) {

        // variables
        $userHasLoggedIn = false;

        // handle API token based logins
        if (isset($token) == true) {
            // get user based on token and status
            $user = $this->read(
                'Users',
                'TOKEN = :TOKEN AND STATUS = :STATUS',
                [
                    ':TOKEN' => $token,
                    ':STATUS' => $this::ACTIVE
                ]
            )->first();

            if ($user != false) {
                $user->loggedIn = true;

                // remove unwanted information
                unset($user->PASSWORD);
                unset($user->TOKEN);
                unset($user->TOTP_SECRET);

                // create the user session and login the user
                $this->setSession('User', $user);
            } else {
                $this->log('warning', 'Invalid token', [$token]);
                // delay brute force attacks
                sleep(1);
            }

            return $user;
        }

        // handle password based logins

        // get user based on username and status
        $user = $this->read(
            'Users',
            'USERNAME = :USERNAME AND STATUS = :STATUS',
            [
                ':USERNAME' => $username,
                ':STATUS' => $this::ACTIVE
            ]
        )->first();

        if ($user != false) {
            // verify user password against stored hash
            $user->loggedIn = password_verify($password, $user->PASSWORD);
            if ($user->loggedIn == false) {
                $this->log('warning', 'Login attempt was not successful', [$username]);
                // delay brute force attacks
                sleep(1);

                return false;
            }

            // remove unwanted information
            unset($user->PASSWORD);
            unset($user->TOKEN);

            // check if user has TOTP enabled
            if ($user->TOTP == $this::ACTIVE) {
                $user->TOTPVerified = false;
            } else {
                // remove the TOTP secret if user TOTP is not enabled for the user
                unset($user->TOTP_SECRET);
            }

            // create the user session and login the user if TOTP is not enabled for this user
            if ($user->TOTP == $this::INACTIVE) {
                $this->setSession('User', $user);
            }
        }

        return $user;
    }

    // handle TOTP authentication for a given user id
    public function verifyOTP($id) {
        // variables
        $userHasLoggedIn = false;

    }

    // generate a randomized API token based on username
    private function generateToken($username) {
        $randomString = '';
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        for($i = 0; $i < 32; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return hash('sha256', __OHCRUD_SECRET__ . (isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : PHP_SAPI) . $username . $randomString . time());
    }

}
