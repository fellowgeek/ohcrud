<?php
// Verify malformed JSON, failed serialization and concurrent cache publication.
$GLOBALS['OHCRUD'] = true;
$directory = $argv[2] ?? sys_get_temp_dir() . '/ohcrud-cache-' . bin2hex(random_bytes(8));
define('__OHCRUD_CACHE_ENABLED__', true);
define('__OHCRUD_CACHE_PATH__', $directory . '/');
define('__APP__', 'cache-tests');
require __DIR__ . '/../../public/ohcrud/Core.php';
$core = new \ohCRUD\Core;
$value = str_repeat('cache payload ', 4096);
if (($argv[1] ?? '') === 'writer') {
    for ($i = 0; $i < 100; $i++) {
        if ($core->setCache('concurrent', $value) === false) {
            exit(1);
        }
    }
    exit(0);
}
try {
    if ($core->getCache('missing') !== false || $core->setCache('valid', ['value' => 'original']) === false) {
        throw new RuntimeException('Cache miss or initial write failed.');
    }
    if ($core->setCache('valid', "\xff") !== false || $core->getCache('valid')->value !== 'original') {
        throw new RuntimeException('Failed serialization replaced a valid entry.');
    }
    $path = $directory . '/' . md5(__APP__ . 'valid') . '.cache';
    file_put_contents($path, '{broken');
    if ($core->getCache('valid') !== false) {
        throw new RuntimeException('Malformed JSON was not treated as a cache miss.');
    }
    $core->setCache('valid', 'expired');
    touch($path, time() - 10);
    if ($core->getCache('valid', 5) !== false) {
        throw new RuntimeException('Expired cache entry accepted.');
    }
    $core->setCache('concurrent', $value);
    $process = proc_open([PHP_BINARY, __FILE__, 'writer', $directory], [], $pipes);
    for ($i = 0; $i < 200; $i++) {
        if ($core->getCache('concurrent') !== $value) {
            throw new RuntimeException('Concurrent reader observed incomplete data.');
        }
    }
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Concurrent writer failed.');
    }
    echo "Cache validation, expiry and concurrent publication passed.\n";
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
}
