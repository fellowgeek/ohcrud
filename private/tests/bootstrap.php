<?php
// Exercise fresh installs, existing-user upgrades and repeat initialization in disposable databases.
$GLOBALS['OHCRUD'] = true;
$productionMode = ($argv[1] ?? '') === 'production';
define('__OHCRUD_DEBUG_MODE__', $productionMode == false);
define('__OHCRUD_LOG_ENABLED__', false);
$testDatabase = null;
$config = json_decode(getenv('OHCRUD_TEST_DB_CONFIG') ?: 'null', true);
if (is_array($config) == false) {
    $testDatabase = tempnam(sys_get_temp_dir(), 'ohcrud-bootstrap-');
    $config = ['DRIVER' => 'SQLITE', 'PERSISTENT_CONNECTION' => false, 'SQLITE_DB' => $testDatabase, 'USERNAME' => '', 'PASSWORD' => ''];
}
define('__OHCRUD_DB_CONFIG__', $config);
$sourcePath = getenv('OHCRUD_TEST_SOURCE') ?: __DIR__ . '/../../public';
require $sourcePath . '/ohcrud/Core.php';
require $sourcePath . '/ohcrud/DB.php';
require $sourcePath . '/ohcrud/Users.php';
try {
    $users = new \ohCRUD\Users;
    if ($productionMode == true) {
        $tables = $config['DRIVER'] === 'SQLITE'
            ? $users->db->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll()
            : $users->db->query('SHOW TABLES')->fetchAll();
        if (count($tables) !== 0) {
            throw new RuntimeException('Ordinary production initialization created schema.');
        }
        // Explicit CLI provisioning still initializes both tables with debug disabled.
        $users = new \ohCRUD\Users(true);
    }
    if ((int) $users->db->query('SELECT COUNT(*) FROM Users')->fetchColumn() !== 0 ||
        (int) $users->db->query('SELECT COUNT(*) FROM AuthState')->fetchColumn() !== 0) {
        throw new RuntimeException('Fresh schema contains unexpected data.');
    }
    $users->db->exec("INSERT INTO Users (USERNAME, EMAIL, HASH, TOKEN) VALUES ('fixture', 'fixture@example.invalid', 'fixture', 'fixture')");
    $users->db->exec("INSERT INTO AuthState (ID, ATTEMPTS, WINDOW_START, LAST_STEP) VALUES ('fixture', 3, 123, 42)");
    $users = new \ohCRUD\Users($productionMode);
    $state = $users->db->query("SELECT * FROM AuthState WHERE ID = 'fixture'")->fetch(PDO::FETCH_ASSOC);
    if ((int) $state['ATTEMPTS'] !== 3 || (int) $state['WINDOW_START'] !== 123 || (int) $state['LAST_STEP'] !== 42 ||
        (int) $users->db->query('SELECT COUNT(*) FROM Users')->fetchColumn() !== 1) {
        throw new RuntimeException('Repeat initialization changed existing data.');
    }

    // Upgrade an existing Users installation whose authentication state table is missing.
    $users->db->exec('DROP TABLE AuthState');
    $users = new \ohCRUD\Users($productionMode);
    $users->db->exec("INSERT INTO AuthState (ID) VALUES ('defaults')");
    $state = $users->db->query("SELECT * FROM AuthState WHERE ID = 'defaults'")->fetch(PDO::FETCH_ASSOC);
    if ((int) $state['ATTEMPTS'] !== 0 || (int) $state['WINDOW_START'] !== 0 || (int) $state['LAST_STEP'] !== -1 ||
        (int) $users->db->query('SELECT COUNT(*) FROM Users')->fetchColumn() !== 1) {
        throw new RuntimeException('Existing-user upgrade failed or schema defaults differ from the migration.');
    }
    echo $config['DRIVER'] . ' schema initialization passed (' . ($productionMode == true ? 'production and explicit CLI' : 'debug') . ").\n";
} finally {
    if ($testDatabase !== null) {
        unlink($testDatabase);
    }
}
