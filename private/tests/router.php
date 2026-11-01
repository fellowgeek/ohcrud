<?php
// Verify endpoint exposure without loading application settings or opening a database.
$GLOBALS['OHCRUD'] = true;
define('__OHCRUD_PERMISSION_ALL__', -1);
require __DIR__ . '/../../public/ohcrud/Core.php';
require __DIR__ . '/../../public/ohcrud/Endpoint.php';
require __DIR__ . '/../../public/ohcrud/Permission.php';
require __DIR__ . '/../../public/ohcrud/Router.php';

#[\ohCRUD\Permission(1)]
class PolicyParent {
    #[\ohCRUD\Endpoint(permission: -1, methods: ['GET'])]
    public function inherited() {}
    public function helper() {}
}
class PolicyChild extends PolicyParent {
    #[\ohCRUD\Endpoint(permission: -1)]
    public function unrestricted() {}
    #[\ohCRUD\Endpoint(permission: 1, methods: ['POST'])]
    private function hidden() {}
    #[\ohCRUD\Endpoint(permission: 1)]
    public static function staticHandler() {}
    #[\ohCRUD\Endpoint(permission: 1)]
    public function __invoke() {}
}
class PolicyOverride extends PolicyParent {
    public function inherited() {}
}
$router = (new ReflectionClass(\ohCRUD\Router::class))->newInstanceWithoutConstructor();
$resolve = new ReflectionMethod($router, 'getEndpointPolicy');
foreach (['helper', 'hidden', 'staticHandler', '__invoke', 'missing', null] as $method) {
    if ($resolve->invoke($router, PolicyChild::class, $method) !== false) {
        throw new RuntimeException('An unexposed method was accepted.');
    }
}
if ($resolve->invoke($router, PolicyOverride::class, 'inherited') !== false) {
    throw new RuntimeException('An unannotated override was exposed.');
}
$inherited = $resolve->invoke($router, PolicyChild::class, 'inherited');
$unrestricted = $resolve->invoke($router, PolicyChild::class, 'unrestricted');
if ($inherited !== ['methods' => ['GET'], 'permissions' => [-1, 1]] ||
    $unrestricted !== ['methods' => null, 'permissions' => [-1, 1]]) {
    throw new RuntimeException('Inherited restrictions or optional verbs were lost.');
}
foreach ([[0, null], ['1', null], [1, []], [1, ['post']]] as [$permission, $methods]) {
    try {
        new \ohCRUD\Endpoint($permission, $methods);
        throw new RuntimeException('Invalid endpoint policy accepted.');
    } catch (InvalidArgumentException $exception) {
        // Invalid declarations must fail closed.
    }
}
echo "Endpoint exposure, inheritance and policy validation passed.\n";
