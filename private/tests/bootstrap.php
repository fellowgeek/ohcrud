<?php
// Debug schema creation must never provision a known administrator password.
$GLOBALS['OHCRUD'] = true;
define('__OHCRUD_DEBUG_MODE__', true);
define('__OHCRUD_LOG_ENABLED__', false);
define('__OHCRUD_DB_CONFIG__', ['DRIVER' => 'SQLITE', 'PERSISTENT_CONNECTION' => false, 'SQLITE_DB' => ':memory:', 'USERNAME' => '', 'PASSWORD' => '']);
require __DIR__ . '/../../public/ohcrud/Core.php';
require __DIR__ . '/../../public/ohcrud/DB.php';
require __DIR__ . '/../../public/ohcrud/Users.php';
$users = new \ohCRUD\Users;
if ((int) $users->db->query('SELECT COUNT(*) FROM Users')->fetchColumn() !== 0) {
    throw new RuntimeException('Schema initialization created an account.');
}
echo "Schema initialization leaves account provisioning explicit.\n";
