<?php
namespace OhCrud;

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
                            `PASSWORD`	TEXT,
                            `FIRSTNAME`	TEXT,
                            `LASTNAME`	TEXT,
                            `GROUP`	INTEGER,
                            `PERMISSIONS`	INTEGER,
                            `TOKEN`	TEXT,
                            `STATUS`	INTEGER
                        );
                    ";
                    $this->run($sql);
                    break;

                case "MYSQL":
                    $tableExists = $this->run("SELECT * FROM information_schema.tables WHERE `table_schema`='" . $this->config["MYSQL_DB"] . "' AND `table_name`= 'Users';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Users` (
                        `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
                        `USERNAME` varchar(128) NOT NULL DEFAULT '',
                        `PASSWORD` varchar(256) NOT NULL DEFAULT '',
                        `FIRSTNAME` varchar(64) NOT NULL DEFAULT '',
                        `LASTNAME` varchar(64) NOT NULL DEFAULT '',
                        `GROUP` int(10) unsigned NOT NULL DEFAULT '0',
                        `PERMISSIONS` int(10) unsigned NOT NULL DEFAULT '0',
                        `TOKEN` varchar(256) NOT NULL DEFAULT '',
                        `STATUS` int(10) unsigned NOT NULL DEFAULT '0',
                        PRIMARY KEY (`ID`),
                        KEY `idx_USERNAME` (`USERNAME`) USING BTREE,
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
                    'PASSWORD' => password_hash(
                        'admin', PASSWORD_BCRYPT, [
                            'cost' => 10
                            ]
                        ),
                    'FIRSTNAME' => 'admin',
                    'LASTNAME' => 'admin',
                    'GROUP' => 1,
                    'PERMISSIONS' => 1,
                    'TOKEN' => $this->generateToken('admin'),
                    'STATUS' => 1
                    ]
                );
            }
        }
    }

    public function login($username, $password) {

        // variables
        $userHasLoggedIn = false;

        $user = $this->read(
            'Users',
            'USERNAME = :USERNAME AND STATUS = :STATUS',
            array(
                'USERNAME' => $username,
                'STATUS' => 1
            )
        )->first();

        if ($user != false) {
            $userHasLoggedIn = password_verify($password, $user->PASSWORD);
            unset($user->PASSWORD);
            unset($user->TOKEN);
            if ($userHasLoggedIn == true) {
                $this->setSession('User', $user);
            }
        }

        if ($userHasLoggedIn == false) {
            $this->log('warning', 'Login attempt was not successful', [$username]);
            sleep(1);
        }

        return $userHasLoggedIn;
    }

    public function logout() {
        $this->unsetSession('User');
        return true;
    }

    private function generateToken($username) {
        $randomString = '';
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        for($i = 0; $i < 32; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return hash('sha256', __OHCRUD_SECRET__ . (isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : PHP_SAPI) . $username . $randomString . time());
    }

}
