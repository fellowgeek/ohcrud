<?php
namespace OhCrud;

// Prevent direct access to this class
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

//  Class DB - Database operations class for OhCrud.
class DB extends \OhCrud\Core {

    // Configuration settings for the database
    public $config = [];

    // Database connection instance
    public $db;

    // Stores the last SQL query executed
    public $SQL = '';

    // Constructor for the DB class.
    public function __construct() {
        // Deserialize the database configuration
        $this->config = unserialize(__OHCRUD_DB_CONFIG__);
        // Define PDO options for database connection
        $options = array(
            \PDO::ATTR_PERSISTENT => $this->config['PERSISTENT_CONNECTION'],
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        );

        try {
            // Determine the database driver and create a connection
            switch($this->config["DRIVER"]) {
                case "SQLITE":
                    $connection = "sqlite:" . $this->config["SQLITE_DB"];
                    break;
                case "MYSQL":
                    $connection = "mysql:host=" . $this->config["MYSQL_HOST"] . ";dbname=" . $this->config["MYSQL_DB"];
                    break;
                default:
                    $this->error('Unsuportted DB Driver! Check the configuration.');
                    $this->output();
            }
            if (isset($connection) == true) {
                // Establish a PDO database connection
                $this->db = new \PDO($connection, $this->config["USERNAME"], $this->config["PASSWORD"], $options);
            }
        } catch(\PDOException $e) {
            // Handle exceptions if the connection fails
            $this->error($e->getMessage());
            $this->output();
        }

        // Unset sensitive connection configuration data
        unset(
            $this->config['PERSISTENT_CONNECTION'],
            $this->config['SQLITE_DB'],
            $this->config['MYSQL_HOST'],
            $this->config['USERNAME'],
            $this->config['PASSWORD']
        );
    }

    // Execute an SQL query with optional parameter binding.
    public function run($sql, $bind=array()) {
        // Trim the SQL query
        $sql = trim($sql);

        if (isset($this->db) == false) {
            // Database connection is not established
            $this->success = false;
            return $this->output();
        }

        if (__OHCRUD_DEBUG_MODE__ == true && __OHCRUD_DEBUG_MODE_SHOW_SQL__ == true) {
            // Store the SQL query for debugging
            $this->SQL = $sql;
        } else {
            unset($this->SQL);
        }

        try {
            // Prepare and execute the query
            $result = $this->db->prepare($sql);
            $this->success = $result->execute($bind);

            if (preg_match("/^SELECT(.*?)/i", $sql) == 1) {
                // If it's a SELECT query, fetch and store the results
                $result->execute($bind);
                $result->setFetchMode(\PDO::FETCH_ASSOC);
                $rows = array();
                while($row = $result->fetch()) {
                    $rows[] = (object) $row;
                }
                $this->data = $rows;
                return $this;
            } else {
                // For non-SELECT queries, store the result
                $this->data = $result;
                $this->data->lastInsertId = $this->db->lastInsertId();
                return $this;
            }
        } catch (\PDOException $e) {
            // Handle database query execution exceptions
            $this->data = false;
            $this->error($e->getMessage());
            return $this->output();
        }
    }

    // Execute an INSERT SQL query
    public function create($table, $data=array()) {
        if (__OHCRUD_DB_STAMP__ == true) {
            $data['CDATE'] = date('Y-m-d H:i:s');
            $data['CUSER'] = isset($_SESSION['User']->ID) == true ? $_SESSION['User']->ID : NULL;
        }

        $fields = $this->filter($table, $data);

        $fieldNames = "";
        foreach ($fields as $field)
            $fieldNames .= "`" . $field . "`,";

        $sql = "INSERT INTO " . $table . " (" . trim($fieldNames, ",") . ") VALUES (:" . implode(", :", $fields) . ");";
        $bind = array();
        foreach ($fields as $field)
            $bind[":$field"] = $data[$field];

        return $this->run($sql, $bind);
    }

    // Execute a SELECT SQL query with optional parameter binding.    
    public function read($table, $where="", $bind=array(), $fields="*") {

        $sql = "SELECT " . $fields . " FROM " . $table;
        if (!empty($where))
            $sql .= " WHERE " . $where;
        $sql .= ";";

        return $this->run($sql, $bind);
    }

    // Execute an UPDATE SQL query with optional parameter binding.
    public function update($table, $data, $where, $bind=array()) {
        if (__OHCRUD_DB_STAMP__ == true) {
            $data['MDATE'] = date('Y-m-d H:i:s');
            $data['MUSER'] = isset($_SESSION['User']->ID) == true ? $_SESSION['User']->ID : NULL;
        }

        $fields = $this->filter($table, $data);
        $fieldSize = sizeof($fields);
        $sql = "UPDATE " . $table . " SET ";
        for($f = 0; $f < $fieldSize; ++$f) {
            if ($f > 0)
                $sql .= ", ";
            $sql .= $fields[$f] . " = :update_" . $fields[$f];
        }
        $sql .= " WHERE " . $where . ";";

        foreach ($fields as $field)
            $bind[":update_$field"] = $data[$field];

        return $this->run($sql, $bind);
    }

    // Execute a DELETE SQL query with optional parameter binding.
    public function delete($table, $where, $bind=array()) {
        $sql = "DELETE FROM " . $table . " WHERE " . $where . ";";
        return $this->run($sql, $bind);
    }

    // Get the first result from $this->data and return as object
    public function first() {
        if (empty($this->data) == false && is_array($this->data) == true) {
            return (object) $this->data[0];
        } else {
            return false;
        }
    }

    // Helper method to filter valid fields for database operations
    private function filter($table, $data) {
        $fields = array();
        $filteredData = array();

        if ($this->config['DRIVER'] == 'SQLITE') {
            // SQLite specific query to get table columns
            $sql = "PRAGMA table_info('" . $table . "');";
            $key = "name";
        } elseif ($this->config['DRIVER'] == 'MYSQL') {
            // MySQL specific query to get table columns
            $sql = "DESCRIBE " . $table . ";";
            $key = "Field";
        } else {
            // Generic query for other database types
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '" . $table . "';";
            $key = "column_name";
        }

        try {
            $statement = $this->db->prepare($sql);
            $statement->execute();
            if ($statement !== false) {
                foreach ($statement as $record) {
                    $fields[] = $record[$key];
                }
                $filteredData = array_values(array_intersect($fields, array_keys($data)));
            }
        } catch (\PDOException $e) {
            // Handle database query exceptions when fetching table columns
            $this->error($e->getMessage());
        }

        // If required, create system columns (CDATE, MDATE, CUSER, MUSER) and add them to valid fields
        if (__OHCRUD_DB_STAMP__ == true && empty($fields) == false) {
            try {
                // Loop through system columns and create them if missing in the table
                if (in_array('CDATE', $fields) == false) {
                    if ($this->config['DRIVER'] == 'SQLITE') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `CDATE` TEXT;");
                    }
                    if ($this->config['DRIVER'] == 'MYSQL') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `CDATE` datetime DEFAULT NULL;");
                    }
                    if ($statement->execute() == true) { $fields[] = 'CDATE'; }
                }

                if (in_array('MDATE', $fields) == false) {
                    if ($this->config['DRIVER'] == 'SQLITE') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `MDATE` TEXT;");
                    }
                    if ($this->config['DRIVER'] == 'MYSQL') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `MDATE` datetime DEFAULT NULL;");
                    }
                    if ($statement->execute() == true) { $fields[] = 'MDATE'; }
                }

                if (in_array('CUSER', $fields) == false) {
                    if ($this->config['DRIVER'] == 'SQLITE') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `CUSER` INTEGER;");
                    }
                    if ($this->config['DRIVER'] == 'MYSQL') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `CUSER` int(10) unsigned DEFAULT NULL;");
                    }
                    if ($statement->execute() == true) { $fields[] = 'CUSER'; }
                }

                if (in_array('MUSER', $fields) == false) {
                    if ($this->config['DRIVER'] == 'SQLITE') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `MUSER` INTEGER;");
                    }
                    if ($this->config['DRIVER'] == 'MYSQL') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `MUSER` int(10) unsigned DEFAULT NULL;");
                    }
                    if ($statement->execute() == true) { $fields[] = 'MUSER'; }
                }
                // Filter data again to include the newly added system columns
                $filteredData = array_values(array_intersect($fields, array_keys($data)));
            } catch (\PDOException $e) {
                // Log a warning if there are issues with adding system columns
                $this->log('warning', $e->getMessage());
            }
        }
        return $filteredData;
    }

}
