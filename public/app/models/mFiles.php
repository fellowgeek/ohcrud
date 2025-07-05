<?php
namespace app\models;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Model mFiles - Represents a file model extending the \ohCRUD\DB class.
class mFiles extends \ohCRUD\DB {

    // The permissions associated with the model.
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__
    ];

    function __construct() {
        parent::__construct();

        if (__OHCRUD_DEBUG_MODE__ == true) {

            // Check if the 'Files' table exists based on the database driver.
            $tableExists = false;
            switch($this->config["DRIVER"]) {
                case "SQLITE":
                    $tableExists = $this->run("SELECT * FROM sqlite_master WHERE `name`='Files';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Files` (
                            `ID`	INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
                            `NAME`	TEXT,
                            `PATH`	TEXT,
                            `SIZE`	INTEGER,
                            `TYPE`	TEXT,
                            `IP`	TEXT,
                            `STATUS`    INTEGER
                        );
                    ";
                    $this->run($sql);
                    break;

                case "MYSQL":
                    $tableExists = $this->run("SELECT * FROM information_schema.tables WHERE `table_schema`='" . $this->config["MYSQL_DB"] . "' AND `table_name`= 'Files';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Files` (
                            `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
                            `NAME` varchar(128) NOT NULL DEFAULT '',
                            `PATH` varchar(256) NOT NULL DEFAULT '',
                            `SIZE` bigint(20) unsigned NOT NULL DEFAULT '0',
                            `TYPE` varchar(32) NOT NULL DEFAULT '',
                            `IP` varchar(32) NOT NULL DEFAULT '',
                            `STATUS` tinyint(1) NOT NULL DEFAULT 0,
                            PRIMARY KEY (`ID`),
                            UNIQUE KEY `idx_PATH` (`PATH`) USING BTREE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                    ";
                    $this->run($sql);
                    break;
            }

            // Seed the 'Files' table if it doesn't exist and if the database setup was successful.
            if ($tableExists === false && $this->success === true) {
                $this->create(
                    'Files',
                    [
                        'NAME' => uniqid()
                    ]
                );
            }
        }
    }

}
