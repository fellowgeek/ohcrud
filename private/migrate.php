<?php
// Provision the configured database and apply pending migrations outside the web router.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(); }
$arguments = array_slice($argv, 1);
if ($arguments === ['--help']) {
    echo "Usage: php private/migrate.php [--status]\n";
    echo "Without arguments, create the configured database and framework tables, then apply pending migrations.\n";
    echo "Use --status to inspect migration history without changing the database.\n";
    exit(0);
}
if ($arguments !== [] && $arguments !== ['--status']) {
    fwrite(STDERR, "Usage: php private/migrate.php [--status]\n");
    exit(1);
}
$GLOBALS['OHCRUD'] = true;
define('__OHCRUD_PERMISSION_ALL__', -1);
$publicPath = getenv('OHCRUD_PUBLIC_PATH') ?: __DIR__ . '/../public';
$statusOnly = $arguments === ['--status'];
try {
    require $publicPath . '/vendor/autoload.php';
    $config = __OHCRUD_DB_CONFIG__;
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    if ($config['DRIVER'] === 'SQLITE') {
        if ($statusOnly == true && is_file($config['SQLITE_DB']) == false) {
            echo "Database has not been initialized.\n";
            exit(0);
        }
        $db = new PDO('sqlite:' . $config['SQLITE_DB'], null, null, $options);
    } elseif ($config['DRIVER'] === 'MYSQL') {
        $db = new PDO('mysql:host=' . $config['MYSQL_HOST'], $config['USERNAME'], $config['PASSWORD'], $options);
        $statement = $db->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :name');
        $statement->execute([':name' => $config['MYSQL_DB']]);
        $databaseName = '`' . str_replace('`', '``', $config['MYSQL_DB']) . '`';
        if ($statement->fetchColumn() === false) {
            if ($statusOnly == true) {
                echo "Database has not been initialized.\n";
                exit(0);
            }
            $db->exec('CREATE DATABASE IF NOT EXISTS ' . $databaseName . ' CHARACTER SET utf8mb4');
        }
        $db->exec('USE ' . $databaseName);
    } else {
        throw new RuntimeException('Unsupported database driver.');
    }
    $migrations = new \ohCRUD\Migrations($db, __DIR__ . '/migrations');
    if ($statusOnly == true) {
        foreach ($migrations->status() as $migration) {
            echo ($migration['applied'] == true ? 'Applied: ' : 'Pending: ') . $migration['name'] . "\n";
        }
        exit(0);
    }
    $applied = $migrations->migrate(function () {
        // Reuse the same schema definitions as debug setup, without creating a default account.
        foreach ([\ohCRUD\Users::class, \app\models\mPages::class, \app\models\mFiles::class] as $class) {
            $model = new $class(true);
            if (count($model->errors) > 0 || $model->success !== true) {
                throw new RuntimeException('Framework table initialization failed. Check private application logs.');
            }
        }
    });
    foreach ($applied as $name) echo 'Applied: ' . $name . "\n";
    echo 'Database ready. ' . count($applied) . " migration(s) applied.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
