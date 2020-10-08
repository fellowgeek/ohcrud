<?php
namespace app\models;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class mPages extends \OhCrud\DB {

    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;

    function __construct() {
        parent::__construct();

        if (__OHCRUD_DEBUG_MODE__ == true) {

            // variables
            $tableExists = false;

            switch($this->config["DRIVER"]) {
                case "SQLITE":
                    $tableExists = $this->run("SELECT * FROM sqlite_master WHERE `name`='Pages';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Pages` (
                            `ID` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
                            `URL` TEXT,
                            `TITLE` TEXT,
                            `TEXT` TEXT,
                            `GROUP` INTEGER,
                            `PERMISSIONS` INTEGER DEFAULT -1,
                            `THEME` TEXT,
                            `LAYOUT` TEXT,
                            `STATUS` INTEGER
                        );
                    ";
                    $this->run($sql);
                    break;

                case "MYSQL":
                    $tableExists = $this->run("SELECT * FROM information_schema.tables WHERE `table_schema`='" . $this->config["MYSQL_DB"] . "' AND `table_name`= 'Pages';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Pages` (
                            `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
                            `URL` varchar(256) NOT NULL DEFAULT '',
                            `TITLE` varchar(256) NOT NULL DEFAULT '',
                            `TEXT` mediumtext NOT NULL,
                            `GROUP` int(10) unsigned NOT NULL DEFAULT '0',
                            `PERMISSIONS` int(10) NOT NULL DEFAULT '-1',
                            `THEME` varchar(32) NOT NULL DEFAULT '',
                            `LAYOUT` varchar(32) NOT NULL DEFAULT '',
                            `STATUS` int(10) unsigned NOT NULL DEFAULT '0',
                            PRIMARY KEY (`ID`),
                            KEY `idx_URL` (`URL`) USING BTREE,
                            KEY `idx_GROUP` (`GROUP`) USING BTREE,
                            KEY `idx_STATUS` (`STATUS`) USING BTREE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                    ";
                    $this->run($sql);
                    break;
            }

            // seed the table
            if ($tableExists == false && $this->success == true) {
                $this->create(
                    'Pages',
                    [
                        'URL' => '/',
                        'TITLE' => 'Home',
                        'TEXT' => 'This is home.',
                        'STATUS' => $this::STATUS_ACTIVE
                    ]
                );
            }
        }
    }

}
