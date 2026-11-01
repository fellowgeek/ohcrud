<?php
// Load dependencies without executing the developer's settings or routes.
$repository = dirname(__DIR__, 2);
require_once $repository . '/public/vendor/composer/ClassLoader.php';
$loader = new \Composer\Autoload\ClassLoader;
foreach (require $repository . '/public/vendor/composer/autoload_psr4.php' as $prefix => $paths) {
    $loader->setPsr4($prefix, $paths);
}
foreach (require $repository . '/public/vendor/composer/autoload_namespaces.php' as $prefix => $paths) {
    $loader->set($prefix, $paths);
}
$loader->addClassMap(require $repository . '/public/vendor/composer/autoload_classmap.php');
$loader->setPsr4('app\\', [getenv('OHCRUD_TEST_ROOT') . '/app']);
$loader->setPsr4('ohCRUD\\', [getenv('OHCRUD_TEST_ROOT') . '/ohcrud']);
$loader->register();
foreach (require $repository . '/public/vendor/composer/autoload_files.php' as $file) {
    if (strpos($file, $repository . '/public/vendor/') === 0) {
        require_once $file;
    }
}
require getenv('OHCRUD_TEST_ROOT') . '/settings.php';
require getenv('OHCRUD_TEST_ROOT') . '/routes.php';
