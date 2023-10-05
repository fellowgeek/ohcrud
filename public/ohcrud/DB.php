<?php
namespace OhCrud;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class DB extends \OhCrud\Core {

    const ACTIVE = 1;
    const INACTIVE = 0;

    public $data = [];
    public $errors = [];
    public $success = true;
    public $outputType = null;
    public $outputHeaders = array();
    public $outputStatusCode = 200;
    public $config = [];
    public $db;
    public $SQL;
    public $SQLParameters;

    public function __construct() {
        $this->config = unserialize(__OHCRUD_DB_CONFIG__);
        $options = array(
            \PDO::ATTR_PERSISTENT => $this->config['PERSISTENT_CONNECTION'],
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        );

        try {
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
                $this->db = new \PDO($connection, $this->config["USERNAME"], $this->config["PASSWORD"], $options);
            }
        } catch(\PDOException $e) {
            $this->error($e->getMessage());
            $this->output();
        }

        unset(
            $this->config['PERSISTENT_CONNECTION'],
            $this->config['SQLITE_DB'],
            $this->config['MYSQL_HOST'],
            $this->config['USERNAME'],
            $this->config['PASSWORD']
        );
    }

    public function run($sql, $bind=array()) {

        $sql = trim($sql);

        if (isset($this->db) == false) {
            $this->success = false;
            return $this->output();
        }

        if (__OHCRUD_DEBUG_MODE__ == true) {
            $this->SQL = $sql;
            $this->SQLParameters = $bind;
        }

        try {
            $result = $this->db->prepare($sql);

            $this->success = $result->execute($bind);

            if (preg_match("/^SELECT(.*?)/i", $sql) == 1) {
                $result->execute($bind);
                $result->setFetchMode(\PDO::FETCH_ASSOC);
                $rows = array();
                while($row = $result->fetch()) {
                    $rows[] = (object) $row;
                }
                $this->data = $rows;
                return $this;
            } else {
                $this->data = $result;
                $this->data->lastInsertId = $this->db->lastInsertId();
                return $this;
            }
        } catch (\PDOException $e) {
            $this->data = false;
            $this->error($e->getMessage());
            return $this->output();
        }
    }

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

    public function read($table, $where="", $bind=array(), $fields="*") {

        $sql = "SELECT " . $fields . " FROM " . $table;
        if (!empty($where))
            $sql .= " WHERE " . $where;
        $sql .= ";";

        return $this->run($sql, $bind);
    }

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

    public function delete($table, $where, $bind=array()) {
        $sql = "DELETE FROM " . $table . " WHERE " . $where . ";";
        return $this->run($sql, $bind);
    }

    public function first() {
        if (empty($this->data) == false && is_array($this->data) == true) {
            return (object) $this->data[0];
        } else {
            return false;
        }
    }

    private function filter($table, $data) {
        $fields = array();
        $filteredData = array();

        if ($this->config['DRIVER'] == 'SQLITE') {
            $sql = "PRAGMA table_info('" . $table . "');";
            $key = "name";
        } elseif ($this->config['DRIVER'] == 'MYSQL') {
            $sql = "DESCRIBE " . $table . ";";
            $key = "Field";
        } else {
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
            $this->error($e->getMessage());
        }

        if (__OHCRUD_DB_STAMP__ == true && empty($fields) == false) {
            try {
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
                $filteredData = array_values(array_intersect($fields, array_keys($data)));
            } catch (\PDOException $e) {
                $this->log('warning', $e->getMessage());
            }
        }
        return $filteredData;
    }

}
