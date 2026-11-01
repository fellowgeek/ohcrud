<?php
// Provision audit fields ahead of writes so framework tables need no runtime ALTER.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

return function ($db, $driver) {
    foreach (['Users', 'Pages', 'Files'] as $table) {
        $rows = $driver === 'sqlite'
            ? $db->query('PRAGMA table_info(`' . $table . '`)')->fetchAll(PDO::FETCH_ASSOC)
            : $db->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        $columns = array_column($rows, $driver === 'sqlite' ? 'name' : 'Field');
        $indexes = $driver === 'mysql'
            ? array_column($db->query('SHOW INDEX FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC), 'Key_name')
            : [];
        foreach (['CDATE', 'MDATE', 'CUSER', 'MUSER'] as $column) {
            $isDate = in_array($column, ['CDATE', 'MDATE'], true);
            $type = $driver === 'sqlite'
                ? ($isDate == true ? 'TEXT' : 'INTEGER')
                : ($isDate == true ? 'datetime DEFAULT NULL' : 'int(10) unsigned DEFAULT NULL');
            if (in_array($column, $columns, true) == false) {
                $db->exec('ALTER TABLE `' . $table . '` ADD `' . $column . '` ' . $type);
            }
            if ($driver === 'mysql' && in_array('ixd_' . $column, $indexes, true) == false) {
                $db->exec('CREATE INDEX `ixd_' . $column . '` ON `' . $table . '` (`' . $column . '`)');
            }
        }
    }
};
