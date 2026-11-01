<?php
// Exercise view boundaries without loading application settings or a database.
$GLOBALS['OHCRUD'] = true;
require __DIR__ . '/../../public/ohcrud/Core.php';
require __DIR__ . '/../../public/ohcrud/DB.php';
require __DIR__ . '/../../public/app/components/Component.php';
$testDirectory = sys_get_temp_dir() . '/ohcrud-component-' . bin2hex(random_bytes(8));
define('__SELF__', $testDirectory . '/');
mkdir($testDirectory . '/app/components/Test/views', 0700, true);
mkdir($testDirectory . '/app/components-other', 0700, true);
file_put_contents($testDirectory . '/app/components/Test/views/test.phtml', '<?php echo $caption;');
file_put_contents($testDirectory . '/app/components-other/secret.phtml', 'UNTRUSTED');
file_put_contents($testDirectory . '/upload.txt', '<?php echo "UNTRUSTED";');
symlink($testDirectory . '/upload.txt', $testDirectory . '/app/components/Test/views/link.phtml');
try {
    $component = (new ReflectionClass(\app\components\Component::class))->newInstanceWithoutConstructor();
    $component->variables = ['caption' => 'SAFE', 'fileName' => 'upload.txt', 'viewPath' => $testDirectory . '/upload.txt'];
    if ($component->loadView('app/components/Test/views/test.phtml') !== 'SAFE') {
        throw new RuntimeException('Component parameters overwrote the trusted template.');
    }
    foreach (['upload.txt', 'app/components/../components-other/secret.phtml', 'app/components/Test/views/link.phtml', 'missing.phtml'] as $path) {
        if ($component->loadView($path) !== false) {
            throw new RuntimeException('Unsafe template was accepted: ' . $path);
        }
    }
    echo "Component rendering and inclusion boundaries passed.\n";
} finally {
    unlink($testDirectory . '/app/components/Test/views/link.phtml');
    unlink($testDirectory . '/app/components/Test/views/test.phtml');
    unlink($testDirectory . '/app/components-other/secret.phtml');
    unlink($testDirectory . '/upload.txt');
    rmdir($testDirectory . '/app/components/Test/views');
    rmdir($testDirectory . '/app/components/Test');
    rmdir($testDirectory . '/app/components');
    rmdir($testDirectory . '/app/components-other');
    rmdir($testDirectory . '/app');
    rmdir($testDirectory);
}
