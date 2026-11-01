<?php
// Verify escaping at component rendering sinks, including URLs and attribute boundaries.
$GLOBALS['OHCRUD'] = true;
require __DIR__ . '/../../public/ohcrud/Core.php';
$core = new \ohCRUD\Core;
$render = function () {
    $image = 'x" onerror="alert(1)';
    $alt = '"><script>alert(1)</script>';
    $caption = '<img src=x onerror=alert(1)>';
    ob_start();
    include __DIR__ . '/../../public/app/components/CMS/Figure/views/FigureView.phtml';
    return ob_get_clean();
};
$html = $render->call($core);
if (strpos($html, '<script>') !== false || strpos($html, '" onerror=') !== false || strpos($html, '&lt;img') === false) {
    throw new RuntimeException('Unsafe component output.');
}
foreach (['javascript:alert(1)', 'java&#x73;cript:alert(1)', 'data:text/html,test', '//example.invalid', "/\\example.invalid"] as $url) {
    if ($core->safeURL($url) !== '') { throw new RuntimeException('Active URL accepted.'); }
}
if ($core->safeURL('/image.png') !== '/image.png' || $core->safeURL('https://example.invalid/') !== 'https://example.invalid/') {
    throw new RuntimeException('Safe URL rejected.');
}
echo "Component text, attributes and URL escaping passed.\n";
