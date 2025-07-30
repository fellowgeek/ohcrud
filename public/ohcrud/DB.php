<?php
namespace ohCRUD;

// Prevent direct access to this class
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

//  Class DB - Database operations class for ohCRUD.
class DB extends \ohCRUD\Core {

    // Stores the most recently generated auto-increment ID from a successful INSERT query.
    public $lastInsertId;

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
    public function run($sql, $bind=array(), $updateSuccess = true) {
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
            $this->SQL = 'Redacted from debug.';
        }

        try {
            // Prepare and execute the query
            $result = $this->db->prepare($sql);
            if ($updateSuccess == true) $this->success = $result->execute($bind); else $result->execute($bind);

            if (preg_match("/^SELECT(.*?)/i", $sql) === 1) {
                // If it's a SELECT query, fetch and store the results
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
                $this->lastInsertId = $this->db->lastInsertId();
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
            $sql .= "`" . $fields[$f] . "` = :update_" . $fields[$f];
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
            return (object) $this->data[0] ?? false;
        } else {
            return false;
        }
    }

    // Return the primary key column name for a given table
    public function getPrimaryKeyColumn($table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            $this->error('Invalid table name.');
            return null;
        }

        try {
            $isSQLite = $this->config["DRIVER"] === 'SQLITE';
            $isMySQL = $this->config["DRIVER"] === 'MYSQL';

            if (!$isSQLite && !$isMySQL) {
                throw new \Exception('Unsupported PDO driver: ' . $this->config["DRIVER"]);
            }

            if ($isSQLite) {
                $stmt = $this->db->query("PRAGMA table_info(`$table`)");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    if ((int)$row['pk'] !== 0) {
                        return $row['name']; // Return first primary key column found
                    }
                }
            } else {
                $stmt = $this->db->query("DESCRIBE `$table`");
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    if ($row['Key'] === 'PRI') {
                        return $row['Field']; // Return first primary key column found
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        // No primary key found or error occurred
        return false;
    }

    // Return the details of all tables or a specific table
    public function details($table = '', $returnColumnDetails = false) {

        $schema = new \stdClass();

        if ($table != '' && preg_match('/^[a-zA-Z0-9_]+$/', $table) == false) {
            $this->error('Invalid table name.');
            return;
        }

        try {
            if ($this->config["DRIVER"] === 'MYSQL' || $this->config["DRIVER"] === 'SQLITE') {

                $isSQLite = $this->config["DRIVER"] === 'SQLITE';
                $tables = [];

                if ($table) {
                    $tables = [$table];
                } else {
                    $stmt = $isSQLite
                        ? $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
                        : $this->db->query("SHOW TABLES");

                    while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                        $tables[] = $row[0];
                    }
                }

                foreach ($tables as $tableName) {
                    $tableInfo = new \stdClass();
                    $tableInfo->NAME = $tableName;

                    // Get row count
                    $rowCountStmt = $this->db->query("SELECT COUNT(*) FROM `$tableName`");
                    $tableInfo->ROW_COUNT = (int) $rowCountStmt->fetchColumn();

                    if ($returnColumnDetails === true) {
                        $columns = [];
                        $fieldsStmt = $isSQLite
                            ? $this->db->query("PRAGMA table_info(`$tableName`)")
                            : $this->db->query("DESCRIBE `$tableName`");

                        while ($fieldRow = $fieldsStmt->fetch(\PDO::FETCH_ASSOC)) {
                            $field = new \stdClass();
                            $field->NAME = $isSQLite ? $fieldRow['name'] : $fieldRow['Field'];
                            $field->TYPE = $isSQLite ? $fieldRow['type'] : $fieldRow['Type'];
                            $field->NULLABLE = $isSQLite ? !$fieldRow['notnull'] : ($fieldRow['Null'] === 'YES');
                            $field->DEFAULT = $isSQLite ? $fieldRow['dflt_value'] : $fieldRow['Default'];
                            $field->PRIMARY_KEY = $isSQLite ? ($fieldRow['pk'] != 0) : ($fieldRow['Key'] === 'PRI');
                            $field->EXTRA = $isSQLite ? null : $fieldRow['Extra'];

                            // Sample data from the column to detect type
                            $sampleStmt = $this->db->prepare(
                                "SELECT `{$field->NAME}` FROM `$tableName` WHERE `{$field->NAME}` IS NOT NULL AND `{$field->NAME}` <> '' LIMIT 5");
                            $sampleStmt->execute();
                            $samples = $sampleStmt->fetchAll(\PDO::FETCH_COLUMN);

                            $detectedTypes = [];
                            foreach ($samples as $sampleValue) {
                                $detectedTypes[] = $this->detectDataType((string)$sampleValue, $field->TYPE);
                            }

                            // Basic majority vote or fallback
                            if (count($detectedTypes)) {
                                $typeCounts = array_count_values($detectedTypes);
                                arsort($typeCounts); // Sort by count descending
                                $field->DETECTED_TYPE = array_key_first($typeCounts);
                            } else {
                                $field->DETECTED_TYPE = 'empty';
                            }
                            $columns[] = $field;
                        }

                        $tableInfo->COLUMNS = $columns;
                    }

                    $schema->$tableName = $tableInfo;
                }

            } else {
                throw new \Exception('Unsupported PDO driver: ' . $this->config["DRIVER"]);
            }

        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return $this->output();
        }

        return $schema;
    }

    // Helper method to filter valid fields for database operations
    private function filter($table, $data) {
        $fields = array();
        $filteredData = array();

        if ($this->config['DRIVER'] === 'SQLITE') {
            // SQLite specific query to get table columns
            $sql = "PRAGMA table_info('" . $table . "');";
            $key = "name";
        } elseif ($this->config['DRIVER'] === 'MYSQL') {
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
                    if ($this->config['DRIVER'] === 'SQLITE') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `CDATE` TEXT;");
                    }
                    if ($this->config['DRIVER'] === 'MYSQL') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `CDATE` datetime DEFAULT NULL; ALTER TABLE `" . $table . "` ADD INDEX `ixd_CDATE` (`CDATE`) USING BTREE;");
                    }
                    if ($statement->execute() == true) { $fields[] = 'CDATE'; }
                }

                if (in_array('MDATE', $fields) == false) {
                    if ($this->config['DRIVER'] === 'SQLITE') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `MDATE` TEXT;");
                    }
                    if ($this->config['DRIVER'] === 'MYSQL') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `MDATE` datetime DEFAULT NULL; ALTER TABLE `" . $table . "` ADD INDEX `ixd_MDATE` (`MDATE`) USING BTREE;");
                    }
                    if ($statement->execute() == true) { $fields[] = 'MDATE'; }
                }

                if (in_array('CUSER', $fields) == false) {
                    if ($this->config['DRIVER'] === 'SQLITE') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `CUSER` INTEGER;");
                    }
                    if ($this->config['DRIVER'] === 'MYSQL') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `CUSER` int(10) unsigned DEFAULT NULL; ALTER TABLE `" . $table . "` ADD INDEX `ixd_CUSER` (`CUSER`) USING BTREE;");
                    }
                    if ($statement->execute() == true) { $fields[] = 'CUSER'; }
                }

                if (in_array('MUSER', $fields) == false) {
                    if ($this->config['DRIVER'] === 'SQLITE') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `MUSER` INTEGER;");
                    }
                    if ($this->config['DRIVER'] === 'MYSQL') {
                        $statement = $this->db->prepare("ALTER TABLE `" . $table . "` ADD `MUSER` int(10) unsigned DEFAULT NULL; ALTER TABLE `" . $table . "` ADD INDEX `ixd_MUSER` (`MUSER`) USING BTREE;");
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

    // Helper method to guess the type of string data based on its value
    private function detectDataType(string $value, string $type): string {

        $value = trim($value);

        // Email
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        // URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return 'URL';
        }

        // IP address
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'IPv4';
        }
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'IPv6';
        }

        // DateTime (MySQL-like)
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return 'datetime';
        }

        // Date
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return 'date';
        }

        // Time
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return 'time';
        }

        // Timestamp (Unix, 10 digits)
        if (preg_match('/^\d{10}$/', $value)) {
            $ts = (int)$value;
            if ($ts > 1000000000 && $ts < 5000000000) {
                return 'timestamp';
            }
        }

        // UUID
        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value)) {
            return 'UUID';
        }

        // Boolean-like
        if (in_array(strtolower($value), ['true', 'false'], true)) {
            return 'boolean-like';
        }

        // Integer
        if (preg_match('/^-?\d+$/', $value)) {
            if (in_array(strtolower($type), ['tinyint(1)','boolean','bool','bit(1)']) == true) {
                return 'boolean';
            }
            return 'integer';
        }

        // Float / Decimal
        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return 'float';
        }

        // Known bcrypt format
        if (preg_match('/^\$2[ayb]\$.{56}$/', $value)) {
            return 'encrypted (bcrypt)';
        }

        // Hash (hex only)
        if (preg_match('/^[a-f0-9]{32,64}$/i', $value)) {
            $length = strlen($value);
            switch ($length) {
                case 32: return 'hash (MD5)';
                case 40: return 'hash (SHA1)';
                case 64: return 'hash (SHA256)';
                default: return 'hash (unknown)';
            }
        }

        // Base64
        if (preg_match('/^[A-Za-z0-9+\/=]+$/', $value) && strlen($value) % 4 === 0) {
            $decoded = base64_decode($value, true);
            if ($decoded !== false && strlen($decoded) > 8) {
                return 'base64';
            }
        }

        // Encryption guess (excluding paths or path-like strings)
        if (strlen($value) !== 0) {
            $hasSlashes = substr_count($value, '/') > 1 && str_starts_with($value, '/');

            $uniqueChars = count(array_unique(str_split($value)));
            $entropy = $uniqueChars / strlen($value);
            $hasSymbols = preg_match('/[^A-Za-z0-9-_\.]/', $value);

            if (!$hasSlashes && strlen($value) >= 16 && $hasSymbols && $entropy > 0.4) {
                return 'encrypted (guessed)';
            }
        }

        return 'string';
    }

}