<?php
namespace ohCRUD;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Apply ordered migrations on one connection and record their immutable checksums.
class Migrations {

    private $db;
    private $directory;
    private $driver;

    // Accept the provisioning connection; ordinary web requests never instantiate this class.
    public function __construct($db, $directory) {
        if (PHP_SAPI !== 'cli') {
            throw new \RuntimeException('Migrations are available only through CLI.');
        }
        $this->db = $db;
        $this->directory = $directory;
        $this->driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (in_array($this->driver, ['sqlite', 'mysql'], true) == false) {
            throw new \RuntimeException('Unsupported migration database driver.');
        }
    }

    // Read migration status without creating tables or applying pending work.
    public function status() {
        if (is_dir($this->directory) == false) {
            throw new \RuntimeException('Migration directory does not exist.');
        }
        $files = glob($this->directory . '/*') ?: [];
        sort($files, SORT_STRING);
        $migrations = [];
        $versions = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (preg_match('/^([0-9]+)[-_][a-zA-Z0-9_-]+\.(sql|php)$/D', $name, $matches) !== 1) {
                continue;
            }
            $version = (int) $matches[1];
            if (isset($versions[$version]) == true) {
                throw new \RuntimeException('Duplicate migration version: ' . $matches[1]);
            }
            $versions[$version] = true;
            $checksum = hash_file('sha256', $file);
            if ($checksum === false) {
                throw new \RuntimeException('Unable to read migration: ' . $name);
            }
            $migrations[$name] = ['name' => $name, 'file' => $file, 'checksum' => $checksum, 'applied' => false, 'version' => $version];
        }
        uasort($migrations, function ($left, $right) {
            return $left['version'] <=> $right['version'];
        });
        $query = $this->driver === 'sqlite'
            ? "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'Migrations'"
            : "SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Migrations'";
        if ($this->db->query($query)->fetchColumn() !== false) {
            foreach ($this->db->query('SELECT NAME, CHECKSUM FROM Migrations')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if (isset($migrations[$row['NAME']]) == false || $migrations[$row['NAME']]['checksum'] !== $row['CHECKSUM']) {
                    throw new \RuntimeException('Applied migration is missing or modified: ' . $row['NAME']);
                }
                $migrations[$row['NAME']]['applied'] = true;
            }
        }
        $latestApplied = -1;
        foreach ($migrations as $migration) {
            if ($migration['applied'] == true) $latestApplied = max($latestApplied, $migration['version']);
        }
        foreach ($migrations as $migration) {
            if ($migration['applied'] == false && $migration['version'] < $latestApplied) {
                throw new \RuntimeException('Pending migration precedes applied history: ' . $migration['name']);
            }
        }
        return array_values($migrations);
    }

    // Serialize provisioning, initialize base tables, then apply each pending migration once.
    public function migrate($initializeSchema) {
        $lock = null;
        $lockName = null;
        if ($this->driver === 'mysql') {
            $lockName = 'ohcrud:' . substr(hash('sha256', $this->db->query('SELECT DATABASE()')->fetchColumn()), 0, 56);
            $statement = $this->db->prepare('SELECT GET_LOCK(:name, 0)');
            $statement->execute([':name' => $lockName]);
            if ((int) $statement->fetchColumn() !== 1) {
                throw new \RuntimeException('Another migration process holds the database lock.');
            }
        } else {
            $databasePath = '';
            foreach ($this->db->query('PRAGMA database_list')->fetchAll(\PDO::FETCH_ASSOC) as $database) {
                if ($database['name'] === 'main') $databasePath = $database['file'];
            }
            if ($databasePath === '') {
                throw new \RuntimeException('Migrations require a file-backed SQLite database.');
            }
            $lock = fopen($databasePath . '.migration.lock', 'c');
            if ($lock === false || flock($lock, LOCK_EX | LOCK_NB) == false) {
                if (is_resource($lock) == true) fclose($lock);
                throw new \RuntimeException('Another migration process holds the database lock.');
            }
        }

        try {
            // Validate history before changing schema or applying any pending migration.
            $migrations = $this->status();
            $initializeSchema();
            $this->db->exec('CREATE TABLE IF NOT EXISTS Migrations (
                NAME VARCHAR(190) NOT NULL PRIMARY KEY,
                CHECKSUM VARCHAR(64) NOT NULL,
                APPLIED_AT VARCHAR(32) NOT NULL
            )');
            $applied = [];
            foreach ($migrations as $migration) {
                if ($migration['applied'] == true) continue;
                try {
                    // SQLite supports transactional DDL; MySQL DDL implicitly commits.
                    if ($this->driver === 'sqlite') $this->db->beginTransaction();
                    if (pathinfo($migration['file'], PATHINFO_EXTENSION) === 'sql') {
                        $this->db->exec(file_get_contents($migration['file']));
                    } else {
                        $apply = require $migration['file'];
                        if (is_callable($apply) == false) {
                            throw new \RuntimeException('PHP migrations must return a callable.');
                        }
                        $apply($this->db, $this->driver);
                    }
                    $record = $this->db->prepare('INSERT INTO Migrations (NAME, CHECKSUM, APPLIED_AT) VALUES (:name, :checksum, :appliedAt)');
                    $record->execute([':name' => $migration['name'], ':checksum' => $migration['checksum'], ':appliedAt' => gmdate('c')]);
                    if ($this->driver === 'sqlite') $this->db->commit();
                    $applied[] = $migration['name'];
                } catch (\Throwable $exception) {
                    if ($this->db->inTransaction() == true) $this->db->rollBack();
                    throw new \RuntimeException('Migration failed: ' . $migration['name'] . '. ' . $exception->getMessage(), 0, $exception);
                }
            }
            return $applied;
        } finally {
            if ($lockName !== null) {
                $statement = $this->db->prepare('SELECT RELEASE_LOCK(:name)');
                $statement->execute([':name' => $lockName]);
            }
            if (is_resource($lock) == true) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
