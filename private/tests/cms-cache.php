<?php
// Exercise real fragment caching and asset bundles in the disposable HTTP fixture.
$GLOBALS['OHCRUD'] = true;
define('__OHCRUD_PERMISSION_ALL__', -1);
define('__OHCRUD_CMS_MINIFY_CSS__', true);
define('__OHCRUD_CMS_MINIFY_JS__', true);
// Load the fixture settings with the two minification flags already selected.
$originalSettings = file_get_contents(getenv('OHCRUD_TEST_ROOT') . '/settings.php');
$settings = $originalSettings;
$settings = str_replace("define('__OHCRUD_CMS_MINIFY_CSS__', false);", '', $settings);
$settings = str_replace("define('__OHCRUD_CMS_MINIFY_JS__', false);", '', $settings);
file_put_contents(getenv('OHCRUD_TEST_ROOT') . '/settings.php', $settings);
try {
    require __DIR__ . '/autoload.php';
} finally {
    file_put_contents(getenv('OHCRUD_TEST_ROOT') . '/settings.php', $originalSettings);
}
$GLOBALS['PATH'] = '/';
$cms = new \app\controllers\cCMS((object) []);
$render = new ReflectionMethod($cms, 'renderMarkdown');
$html = $render->invoke($cms, '**cache fixture** <script>alert(1)</script>');
if (strpos($html, '<strong>cache fixture</strong>') === false || strpos($html, '<script>') !== false) {
    throw new RuntimeException('Markdown was not rendered and sanitized.');
}
$warm = new \app\controllers\cCMS((object) []);
if ($render->invoke($warm, '**cache fixture** <script>alert(1)</script>') !== $html || $warm->markdownExtra !== null) {
    throw new RuntimeException('Warm rendering did not reuse the sanitized fragment.');
}
if ($render->invoke($warm, '**changed fixture**') === $html || $warm->allowedComponents !== null || $warm->pages !== $warm) {
    throw new RuntimeException('Changed content, lazy scanning or connection reuse failed.');
}

// Build small bundles, then confirm warm requests skip minifier construction.
$assetRoot = getenv('OHCRUD_TEST_ROOT') . '/global';
file_put_contents($assetRoot . '/fixture.css', 'body { color: red; }');
file_put_contents($assetRoot . '/fixture.js', 'window.fixture = 1;');
$warm->includeCSSFile('/global/fixture.css');
$warm->includeJSFile('/global/fixture.js');
$css = new ReflectionMethod($warm, 'getCSSAssets');
$js = new ReflectionMethod($warm, 'getJSAssets');
$css->invoke($warm);
$js->invoke($warm);
$style = $warm->content->stylesheet;
$script = $warm->content->javascript;
$warm->minifierCSS = null;
$warm->minifierJS = null;
$css->invoke($warm);
$js->invoke($warm);
if ($warm->content->stylesheet !== $style || $warm->content->javascript !== $script ||
    $warm->minifierCSS !== null || $warm->minifierJS !== null) {
    throw new RuntimeException('Warm bundles were regenerated.');
}
file_put_contents($assetRoot . '/fixture.css', 'body { color: blue; }');
clearstatcache();
$css->invoke($warm);
if ($warm->content->stylesheet === $style) {
    throw new RuntimeException('Changed asset did not generate a new bundle.');
}
$warm->includeCSSFile('/global/missing.css');
$css->invoke($warm);
if (strpos($warm->content->stylesheet, '/global/fixture.css') === false) {
    throw new RuntimeException('Missing asset did not fall back to individual links.');
}
echo "Sanitized fragment cache and versioned asset bundles passed.\n";
