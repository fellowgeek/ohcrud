<?php
// Test return-path validation and login rendering without a database.
$GLOBALS['OHCRUD'] = true;
require __DIR__ . '/../../public/ohcrud/Core.php';
$core = new \ohCRUD\Core;
foreach (['https://example.invalid', '//example.invalid', '/\\example.invalid', '/%2fexample.invalid', '/%5cexample.invalid', "/\nexample.invalid", 'javascript:alert(1)', [], null] as $url) {
    if ($core->localRedirect($url) !== '/') {
        throw new RuntimeException('Unsafe redirect accepted.');
    }
}
if ($core->localRedirect('/articles/?action=edit') !== '/articles/?action=edit') {
    throw new RuntimeException('Local return path changed.');
}
$_REQUEST['redirect'] = '/"><script>window.audit=1</script>';
$render = function () {
    ob_start();
    include __DIR__ . '/../../public/app/views/cms/login.phtml';
    return ob_get_clean();
};
$html = $render->call($core);
if (strpos($html, '<script>window.audit=1</script>') !== false || strpos($html, '&quot;&gt;&lt;script&gt;') === false) {
    throw new RuntimeException('Login attribute was not escaped.');
}
echo "Login escaping and local redirects passed.\n";
