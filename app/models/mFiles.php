<?php
namespace app\models;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class mFiles extends \OhCrud\DB {

    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__
    ];

    function __construct() {
        parent::__construct();

        if (__OHCRUD_DEBUG_MODE__ == true) {

            // variables
            $tableExists = false;

            switch($this->config["DRIVER"]) {
                case "SQLITE":
                    $tableExists = $this->run("SELECT * FROM sqlite_master WHERE `name`='Files';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Files` (
                            `ID`	INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
                            `NAME`	TEXT,
                            `TITLE`	TEXT,
                            `PATH`	TEXT,
                            `SIZE`	INTEGER,
                            `TYPE`	TEXT,
                            `IP`	TEXT,
                            `STATUS`	INTEGER
                        );
                    ";
                    $this->run($sql);
                    break;

                case "MYSQL":
                    $tableExists = $this->run("SELECT * FROM information_schema.tables WHERE `table_schema`='" . $this->config["MYSQL_DB"] . "' AND `table_name`= 'Files';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Files` (
                            `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
                            `NAME` varchar(256) NOT NULL DEFAULT '',
                            `TITLE` varchar(256) NOT NULL DEFAULT '',
                            `PATH` varchar(256) NOT NULL DEFAULT '',
                            `SIZE` bigint(20) unsigned NOT NULL DEFAULT '0',
                            `TYPE` varchar(32) NOT NULL DEFAULT '',
                            `IP` varchar(32) NOT NULL DEFAULT '',
                            `STATUS` int(10) unsigned NOT NULL DEFAULT '0',
                            PRIMARY KEY (`ID`)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                    ";
                    $this->run($sql);
                    break;
            }

            // seed the table
            if ($tableExists == false && $this->success == true) {
                $this->create(
                    'Files',
                    [
                        'URL' => '/',
                        'NAME' => uniqid()
                    ]
                );
            }
        }
    }

}
