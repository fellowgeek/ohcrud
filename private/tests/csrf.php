<?php
// CSRF behavior must remain strict even with debugging enabled.
$GLOBALS['OHCRUD'] = true;
define('__OHCRUD_DEBUG_MODE__', true);
require __DIR__ . '/../../public/ohcrud/Core.php';
$core = new \ohCRUD\Core;
$_SESSION = [];
if ($core->checkCSRF('') !== false) { throw new RuntimeException('Missing CSRF accepted.'); }
$_SESSION['CSRF'] = 'test-token';
foreach (['', 'wrong', [], null] as $token) {
    if ($core->checkCSRF($token) !== false) { throw new RuntimeException('Invalid CSRF accepted.'); }
}
if ($core->checkCSRF('test-token') !== true) { throw new RuntimeException('Valid CSRF rejected.'); }
echo "CSRF remains enforced in debug mode.\n";
