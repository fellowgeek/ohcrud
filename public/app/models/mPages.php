<?php
namespace app\models;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Model mPages - Represents a pages model extending the \ohCRUD\DB class.
class mPages extends \ohCRUD\DB {

    function __construct() {
        parent::__construct();

        if (__OHCRUD_DEBUG_MODE__ == true) {

            // Check if the 'Pages' table exists in the database, and create it if it doesn't.
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
                            `STATUS` tinyint(1) NOT NULL DEFAULT 0,
                            PRIMARY KEY (`ID`),
                            UNIQUE KEY `idx_URL` (`URL`) USING BTREE,
                            KEY `idx_GROUP` (`GROUP`) USING BTREE,
                            KEY `idx_STATUS` (`STATUS`) USING BTREE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                    ";
                    $this->run($sql);
                    break;
            }

            // If the 'Pages' table was created and the operation was successful, seed the table with a default page.
            if ($tableExists == false && $this->success == true) {
                $this->create(
                    'Pages',
                    [
                        'URL' => '/',
                        'TITLE' => 'Home',
                        'TEXT' => 'This is home.',
                        'THEME' => __OHCRUD_CMS_DEFAULT_THEME__,
                        'LAYOUT' => __OHCRUD_CMS_DEFAULT_LAYOUT__,
                        'STATUS' => $this::ACTIVE
                    ]
                );
            }
        }
    }

}
