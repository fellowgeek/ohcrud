<?php
namespace app\controllers;

use HTMLPurifier;
use Michelf\MarkdownExtra;
use MatthiasMullie\Minify;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cCMS - CMS controller used by the OhCRUD framework
class cCMS extends \app\models\mPages {

    const SAFE_IFRAME_PATTERN = '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%';

    // The path of the requested content.
    public $path;
    // The content of the page.
    public $content;
    // The theme of the page.
    public $theme = __OHCRUD_CMS_DEFAULT_THEME__;
    // The layout of the page.
    public $layout = __OHCRUD_CMS_DEFAULT_LAYOUT__;
    // Meta tags to include.
    public $metaTags = [];
    // CSS files to include.
    public $cssFiles = [];
    // JavaScript files to include.
    public $jsFiles = [];
    // Flag indicating whether the user is in action mode.
    public $actionMode = false;
    // Flag indicating whether should use cache.
    public $useCache = true;
    // Flag indicating whether the user is logged in.
    public $loggedIn = false;
    // Flag indicating whether the user is an admin.
    public $isAdmin = false;
    // Request data.
    public object $request;
    // Instance for managing pages.
    public $pages;
    // Markdown processor.
    public $markdownExtra;
    // HTML purifier for security.
    public $purifier;
    // Minifier instances for CSS and JS.
    public $minifierCSS;
    public $minifierJS;
    // Recursive content counter.
    public $recursiveContentCounter = 0;
    // Max recursive content
    public $maxRecursiveContent = 7;
    // Allowed CMS actions
    public $allowedActions = ['edit', 'users', 'tables', 'files', 'sql', 'server', 'logs'];
    // Allowed components for instantiation
    public $allowedComponents = null;

    public function __construct($request) {
        parent::__construct();

        $this->request = $request;
        $this->content = new \app\models\mContent;
        // The inherited page model already owns the database connection.
        $this->pages = $this;

        // Set login status
        $this->loggedIn = isset($_SESSION['User']);

        // Capture and validate action
        $action = $this->request->action ?? '';

        // Check if action is in allowed list AND user has admin permissions
        $this->isAdmin = $this->loggedIn && $_SESSION['User']->PERMISSIONS === 1;

        if (in_array($action, $this->allowedActions) && $this->isAdmin) {
            $this->actionMode = $action;
        } else {
            $this->actionMode = false;
        }

        // Handle logic for active action modes
        if ($this->actionMode !== false) {
            // We already know they are logged in and an admin if actionMode is not false,
            // but we check loggedIn here for clarity or future-proofing.
            if ($this->loggedIn) {
                $this->useCache = false;
            } else {
                $loginUrl = '/login/?redirect=' . $GLOBALS['PATH'] . '?action=' . $action;
                $this->redirect($loginUrl);
                return;
            }
        }

        // Disable cache for login pages
        if ($GLOBALS['PATH'] === '/login/') {
            $this->useCache = false;
        }

        // Templates also use the purifier, including hard-coded error and admin views.
        $this->purifier = new HTMLPurifier();
        $this->purifier->config->set('HTML.SafeIframe', true);
        $this->purifier->config->set('URI.SafeIframeRegexp', self::SAFE_IFRAME_PATTERN);
    }

    // Cache only purified Markdown; permission checks and component rendering stay live.
    private function renderMarkdown($text) {
        $iframePattern = self::SAFE_IFRAME_PATTERN;
        $cacheKey = 'cCMS:markdown:' . hash('sha256', json_encode([
            'renderer-v1', MarkdownExtra::MARKDOWNLIB_VERSION, HTMLPurifier::VERSION,
            $iframePattern, $text,
        ], JSON_THROW_ON_ERROR));
        $html = $this->useCache == true ? $this->getCache($cacheKey, __OHCRUD_CMS_CACHE_DURATION__) : false;
        if (is_string($html) == true) {
            return $html;
        }
        if (isset($this->markdownExtra) == false) {
            $this->markdownExtra = new MarkdownExtra();
            $this->markdownExtra->enhanced_ordered_list = true;
        }
        if (isset($this->purifier) == false) {
            $this->purifier = new HTMLPurifier();
            $this->purifier->config->set('HTML.SafeIframe', true);
            $this->purifier->config->set('URI.SafeIframeRegexp', $iframePattern);
        }
        $html = $this->purifier->purify($this->markdownExtra->transform(preg_replace('/~~(.*?)~~/i', '<del>$1</del>', $text)));
        if ($this->useCache == true) {
            $this->setCache($cacheKey, $html);
        }
        return $html;
    }

    // Handler for all incoming requests
    public function defaultPathHandler($path) {

        $this->setOutputType(\ohCRUD\Core::OUTPUT_HTML);

        // Normalize path
        $this->path = \strtolower($path);

        // Render for each request: pages, embedded content and components can depend
        // on current permissions or session state. Shared HTML must not bypass them.

        // Include application javascript & css files
        $this->includeCSSFile('/global/css/global.css', 1);
        $this->includeJSFile('/global/js/global.js', 1);

        // Get content and set theme & layout from content
        $this->content = $this->getContent($this->path);

        // Set theme and layout
        $this->theme = $this->content->theme;
        $this->layout = $this->content->layout;

        // Process embedded content & components
        $this->content = $this->processContent($this->content);

        //process code blocks for <code class="xxx"> change to <code class="xxx language-xxx"> for better syntax highlighting support
        $this->content->html = preg_replace('/<code class="(.*?)"/i', '<code class="language-$1 $1"', $this->content->html);
        $this->content->html = preg_replace('/<pre><code>/i', '<pre><code class="language-plain plain">', $this->content->html);

        // Process theme & layout
        $this->processTheme();

        // Inject the uncachable content
        $this->data = str_ireplace("{{CMS:UNCACHABLE-HTML}}", $this->getUnCachableContentHTML(), $this->data);
        $this->data = str_ireplace("{{CMS:UNCACHABLE-JS}}", $this->getUnCachableContentJS(), $this->data);

        // Output the HTML page
        $this->output();
    }

    // Include meta tags
    public function includeMetaTags($tag, $value) {
        $this->metaTags[$tag] = $value;
    }

    // Get meta tags
    private function getMetaTags() {
        $this->content->metaTags = '';
        foreach ($this->metaTags as $tag => $value) {
            $this->content->metaTags .= '<meta name="' . $this->escapeHTML($tag) . '" content="' . $this->escapeHTML($value) . '">' . "\n";
        }
    }

    // Include CSS file(s)
    public function includeCSSFile($file, $priority = 100) {
        if (isset($this->cssFiles[$file]) == false) {
            $this->cssFiles[$file] = $priority;
            asort($this->cssFiles);
        }
    }

    // Get CSS assets
    private function getCSSAssets() {
        $this->content->stylesheet = '';

        // If __OHCRUD_CMS_MINIFY_CSS__ is enabled, first calculate a combined hash of all CSS files
        $basePath = rtrim(__SELF__, '/');
        $basePathMinified = __SELF__ . '/global/minified/';
        if (__OHCRUD_CMS_MINIFY_CSS__ == true) {
            $combinedHash = $this->getCombinedFilesHash($basePath, array_keys($this->cssFiles), 'sha1');
            if ($combinedHash !== false) {
                if (file_exists($basePathMinified . $combinedHash . '.min.css') == true) {
                    // If the combined minified file already exists, use it
                    $this->content->stylesheet .= '<link rel="stylesheet" href="/global/minified/' . $combinedHash . '.min.css" media="all" />' . "\n";
                    return;
                }

                // Build the minifier only when the versioned bundle is missing.
                $this->minifierCSS = new Minify\CSS();
                // If the combined minified file does not exist, create it
                foreach ($this->cssFiles as $cssFile => $priority) {
                    $this->minifierCSS->add($basePath . $cssFile);
                }
                if (is_dir($basePathMinified) == false) {
                    mkdir($basePathMinified, 0755, true);
                }
                $temporaryPath = tempnam($basePathMinified, '.bundle-');
                if ($temporaryPath === false) {
                    throw new \RuntimeException('Unable to create an asset bundle.');
                }
                try {
                    $this->minifierCSS->minify($temporaryPath);
                    chmod($temporaryPath, 0644);
                    if (rename($temporaryPath, $basePathMinified . $combinedHash . '.min.css') == false) {
                        throw new \RuntimeException('Unable to publish an asset bundle.');
                    }
                } finally {
                    if (is_file($temporaryPath) == true) {
                        unlink($temporaryPath);
                    }
                }

                // Use the combined minified file
                $this->content->stylesheet .= '<link rel="stylesheet" href="/global/minified/' . $combinedHash . '.min.css" media="all" />' . "\n";
                return;
            }
        }

        foreach ($this->cssFiles as $cssFile => $priority) {
            $this->content->stylesheet .= '<link rel="stylesheet" href="' . $cssFile . '" media="all" />' . "\n";
        }
    }

    // Include Javascript file(s)
    public function includeJSFile($file, $priority = 100) {
        if (isset($this->jsFiles[$file]) == false) {
            $this->jsFiles[$file] = $priority;
            asort($this->jsFiles);
        }
    }

    // Get Javascript assets
    private function getJSAssets() {
        $this->content->javascript = '';

        // If __OHCRUD_CMS_MINIFY_JS__ is enabled, first calculate a combined hash of all JS files
        $basePath = rtrim(__SELF__, '/');
        $basePathMinified = __SELF__ . '/global/minified/';
        if (__OHCRUD_CMS_MINIFY_JS__ == true) {
            $combinedHash = $this->getCombinedFilesHash($basePath, array_keys($this->jsFiles), 'sha1');
            if ($combinedHash !== false) {
                if (file_exists($basePathMinified . $combinedHash . '.min.js') == true) {
                    // If the combined minified file already exists, use it
                    $this->content->javascript .= '<script src="/global/minified/' . $combinedHash . '.min.js"></script>' . "\n";
                    return;
                }

                // Build the minifier only when the versioned bundle is missing.
                $this->minifierJS = new Minify\JS();
                // If the combined minified file does not exist, create it
                foreach ($this->jsFiles as $jsFile => $priority) {
                    $this->minifierJS->add($basePath . $jsFile);
                }
                if (is_dir($basePathMinified) == false) {
                    mkdir($basePathMinified, 0755, true);
                }
                $temporaryPath = tempnam($basePathMinified, '.bundle-');
                if ($temporaryPath === false) {
                    throw new \RuntimeException('Unable to create an asset bundle.');
                }
                try {
                    $this->minifierJS->minify($temporaryPath);
                    chmod($temporaryPath, 0644);
                    if (rename($temporaryPath, $basePathMinified . $combinedHash . '.min.js') == false) {
                        throw new \RuntimeException('Unable to publish an asset bundle.');
                    }
                } finally {
                    if (is_file($temporaryPath) == true) {
                        unlink($temporaryPath);
                    }
                }

                // Use the combined minified file
                $this->content->javascript .= '<script src="/global/minified/' . $combinedHash . '.min.js"></script>' . "\n";
                return;
            }
        }

        foreach ($this->jsFiles as $jsFile => $priority) {
            $this->content->javascript .= '<script src="' . $jsFile . '"></script>' . "\n";
        }
    }

    // Version bundles from ordered source paths and file metadata without reading each asset.
    private function getCombinedFilesHash($basePath, $files, $algo = 'sha1') {
        $signatures = [defined('__OHCRUD_CMS_ASSET_VERSION__') == true ? __OHCRUD_CMS_ASSET_VERSION__ : ''];
        foreach ($files as $file) {
            $path = $basePath . $file;
            if (is_file($path) == false || is_readable($path) == false) {
                return false;
            }
            $stat = stat($path);
            if ($stat === false) {
                return false;
            }
            $signatures[] = [$file, $stat['ino'], $stat['size'], $stat['mtime'], $stat['ctime']];
        }
        return hash($algo, json_encode($signatures, JSON_THROW_ON_ERROR));
    }

    // Load content
    private function getContent($path, $shouldSetOutputStatusCode = true) {

        $content = new \app\models\mContent;

        // Try getting page content from file
        if ($this->isHardCoded($path) == true) {
            $content = $this->getContentFromFile($path);
            // Handle special paths
            if ($path === '/login/') {
                // Set theme and layout
                $content->theme = __OHCRUD_CMS_ADMIN_THEME__;
                $content->layout = 'login';
            }
            return $content;
        }

        // Try getting page content from database
        $page = $this->read(
            'Pages',
            'URL = :URL',
            [
                ':URL' =>  $path
            ]
        )->first();

        // Handle non-existing page
        if ($page === false) {
            if ($shouldSetOutputStatusCode == true) $this->outputStatusCode = 404;

            $content->title = trim(ucwords(str_replace('/', ' ', $path)));
            if (($this->actionMode ?? '') !== 'edit') {
                $content = $this->getContentFromFile($path, 404);
            }
            $content->statusCode = 404;
            return $content;
        }

        // Get page content from database
        $content->type = \app\models\mContent::TYPE_DB;
        $content->title = $page->TITLE;
        $content->theme = $page->THEME;
        $content->layout = $page->LAYOUT;
        $content->status = (int) $page->STATUS;

        // Check if user has permission
        $page->PERMISSIONS = (int) $page->PERMISSIONS;
        $userPermissions = (isset($_SESSION['User']->PERMISSIONS) == true) ? (int) $_SESSION['User']->PERMISSIONS : false;

        // Check if page is public
        if ($page->PERMISSIONS !== __OHCRUD_PERMISSION_ALL__) {
            // Check if user is logged in
            if ($userPermissions === false) {
                $content = $this->getContentFromFile($path, 403);
                $content->statusCode = 403;
                if ($shouldSetOutputStatusCode == true) $this->outputStatusCode = 403;
                return $content;
            }
            // Check if user has the right permission to see the page
            if ($page->PERMISSIONS < $userPermissions) {
                $content = $this->getContentFromFile($path, 403);
                $content->statusCode = 403;
                if ($shouldSetOutputStatusCode == true) $this->outputStatusCode = 403;
                return $content;
            }
        }

        // Handle page status
        switch (($page->STATUS ?? 0)) {
            case $this::PUBLISHED:
                // Published page
                $content->text = $page->TEXT;
                $content->html = $this->renderMarkdown($page->TEXT);
                break;
            case $this::DRAFT:
                // Allow editing draft pages
                if ($this->actionMode == 'edit') {
                    $content->text = $page->TEXT;
                    break;
                }
                // Draft page
                $content = $this->getContentFromFile($path, 404);
                $content->statusCode = 404;
                if ($shouldSetOutputStatusCode == true) $this->outputStatusCode = 404;
                break;
            case $this::DELETED:
                if ($this->actionMode == 'edit') {
                    $content->isDeleted = true;
                    break;
                }
                // Deleted page
                $content = $this->getContentFromFile($path, 404);
                $content->statusCode = 404;
                $content->isDeleted = true;
                if ($shouldSetOutputStatusCode == true) $this->outputStatusCode = 404;
                break;
            default:
                $content = $this->getContentFromFile($path, 404);
                $content->statusCode = 404;
                // Unknown status, treat as not found
                if ($shouldSetOutputStatusCode == true) $this->outputStatusCode = 404;
                break;
        }

        return $content;
    }

    // Load hard-coded content
    private function getContentFromFile($path, $statusCode = 200) {
        $content = new \app\models\mContent;
        $content->type = \app\models\mContent::TYPE_FILE;
        $content->title = ucwords(trim($path, '/'));
        $content->statusCode = $statusCode;

        // Determine the final path to include
        $finalPath = $statusCode !== 200 ? (string) $statusCode : $path;
        $viewPath = 'app/views/cms/' . trim($finalPath, '/') . '.phtml';
        $fullPath = __SELF__ . $viewPath;

        // Mitigate path traversal.
        $baseDir = realpath(__SELF__ . 'app/views/cms');
        $realFullPath = realpath($fullPath);
        if ($realFullPath === false || ($baseDir === false || strpos($realFullPath, $baseDir . DIRECTORY_SEPARATOR) !== 0)) {
            $this->log('warn', 'Local File Inclusion (LFI) attempt blocked.', ['path' => $path]);
            $realFullPath = __SELF__ . 'app/views/cms/404.phtml';
            $content->statusCode = 404;
        }

        ob_start();
        include($realFullPath);
        $content->text = ob_get_clean();
        $content->html = $content->text;

        return $content;
    }

    // Process embedded content and components
    private function processContent($content) {
        // Skip processing when in action mode
        if ($this->actionMode == true) {
            return $content;
        }

        // Check for embedded content
        $regex = '/(?<=\{{)(?!CMS:(.*?)).*?(?=\}})/i';
        $matches = [];
        $matchCount = preg_match_all($regex, $content->html, $matches);

        // If embedded content is found, process it
        if ($matchCount > 0) {
            $this->recursiveContentCounter++;
            foreach ($matches[0] as $match) {
                if ($this->path === '/' . $match . '/' || $this->recursiveContentCounter > $this->maxRecursiveContent) {
                    $content->html = str_ireplace('{{' . $match . '}}', '<mark>ohCRUD! Recursive content not allowed.</mark>', $content->html);
                    continue;
                }
                $embeddedContent = $this->getContent('/' . $match . '/', false);
                if ($embeddedContent->statusCode == 404) {
                    $content->html = str_ireplace('{{' . $match . '}}', '<mark>ohCRUD! Content not found.</mark>', $content->html);
                    continue;
                }
                $content->html = str_ireplace('{{' . $match . '}}', $embeddedContent->html, $content->html);
            }
            // Check for resursive embedded content
            $matchCount = $matchCount = $this->getContentPatternMatchCount($content->html);
            if ($matchCount > 0) {
                $content = $this->processContent($content);
            }
        }

        // Check for components
        $regex = '/(?<=\[\[)(.*?)(?=\]\])/is';
        $matches = [];
        $matchCount = preg_match_all($regex, $content->html, $matches);

        // If component is found, process it
        if ($matchCount > 0) {
            $this->recursiveContentCounter++;
            foreach ($matches[0] as $match) {
                if ($this->recursiveContentCounter > $this->maxRecursiveContent) {
                    $content->html = str_ireplace('[[' . $match . ']]', '<mark>ohCRUD! Recursive content not allowed.</mark>', $content->html);
                    continue;
                }
                $embeddedContent = $this->getComponent($match, false);
                if ($embeddedContent->statusCode == 404) {
                    $content->html = str_ireplace('[[' . $match . ']]', '<mark>ohCRUD! Component not found.</mark>', $content->html);
                    continue;
                }
                // Cleanup parsedown extra paragraphs (if exists)
                $content->html = str_ireplace('<p>[[' . $match . ']]</p>', '[[' . $match . ']]', $content->html);
                // Replace the component code with the component output
                $content->html = str_ireplace('[[' . $match . ']]', $embeddedContent->html, $content->html);
            }
            // Check for resursive components
            $matchCount = $this->getContentPatternMatchCount($content->html);
            if ($matchCount > 0) {
                $content = $this->processContent($content);
            }
        }

        $this->recursiveContentCounter = 0;
        return $content;
    }

    // This function is used to count the number of content patterns in a text
    private function getContentPatternMatchCount($text) {
        $matchCount = 0;
        $regex = '/(?<=\{{)(?!CMS:(.*?)).*?(?=\}})/i';
        $matchCount += preg_match_all($regex, $text);
        $regex = '/(?<=\[\[)(.*?)(?=\]\])/is';
        $matchCount += preg_match_all($regex, $text);

        return $matchCount;
    }

    // Load component(s)
    private function getComponent($componentString, $shouldSetOutputStatusCode = true) {

        $content = new \app\models\mContent;
        $componentParameters = $this->parseString($componentString);
        $componentClassFile = str_replace('\\', '/', key($componentParameters));

        // Scan once, and only on requests that actually render components.
        if ($this->allowedComponents === null) {
            $this->allowedComponents = $this->scanComponents(__SELF__ . 'app/components');
        }

        // Check if the component is in the whitelist.
        if (in_array($componentClassFile, $this->allowedComponents) == false) {
            $this->log('warn', 'Component not in whitelist blocked.', ['component' => $componentClassFile]);
            $content->statusCode = 404;
            return $content;
        }

        $componentClass = '\app\components\\' . str_replace('/', '\\', $componentClassFile);
        array_shift($componentParameters);

        // Check if the component exists
        if (file_exists(__SELF__ . 'app/components/' . $componentClassFile . '.php') == true && class_exists($componentClass) == true) {

            $component = new $componentClass($this->request, $this->path);
            $component->output($componentParameters);
            $content = $component->content;

            // Get content meta tags
            foreach ($component->metaTags as $metaTag => $value) {
                $this->includeMetaTags($metaTag, $value);
            }
            // Get component CSS assets
            foreach ($component->cssFiles as $cssFile => $priority) {
                $this->includeCSSFile($cssFile, $priority);
            }
            // Get component Javascript assets
            foreach ($component->jsFiles as $jsFile => $priority) {
                $this->includeJSFile($jsFile, $priority);
            }

        } else {
            $content->statusCode = 404;
        }

        return $content;
    }

    // Parse component string into an associative array
    private function parseString(string $input_string) {
        $result = [];

        // Split the string by the pipe '|' delimiter.
        $parts = explode('|', $input_string);

        if (empty($parts)) {
            return $result;
        }

        // Process all parts, which can be 'key=value' or 'key' format.
        foreach ($parts as $part) {
            $kv_pair = explode('=', $part, 2);

            $key = trim($kv_pair[0]);
            $value = count($kv_pair) === 2 ? trim($kv_pair[1]) : '';

            // Add the key-value pair to the result.
            // It's a key with an empty string value if there's no '='.
            if ($key !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    // Get uncachable content HTML
    private function getUnCachableContentHTML() {
        $output = '';
        $path = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $this->path);

        if ($this->isAdmin == true) {
            $output.= '<div id="btnCMSEdit" data-url="' . $path . '?action=edit"></div>';
        }

        return $output;
    }

    // Get uncachable content JS
    private function getUnCachableContentJS() {
        $output = '';
        $path = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $this->path);

        // Include Javascript constants and assets
        $output .= "<script>\n";
        $output .= "const __SITE__ = " . json_encode(__SITE__, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";\n";
        $output .= "const __DOMAIN__ = " . json_encode(__DOMAIN__, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";\n";
        $output .= "const __SUB_DOMAIN__ = " . json_encode(__SUB_DOMAIN__, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";\n";
        $output .= "const __PATH__ = '" . $path . "';\n";
        $output .= "const __OHCRUD_BASE_API_ROUTE__ = '" . __OHCRUD_BASE_API_ROUTE__ . "';\n";
        $output .= "const __OHCRUD_DEBUG_MODE__ = " . (__OHCRUD_DEBUG_MODE__ ? 'true' : 'false') . ";\n";
        $output .= "const __CSRF__ = '" . $this->CSRF() . "';\n";
        $output .= "const __LOGGED_IN__ = " . ($this->loggedIn ? 'true' : 'false') . ";\n";
        $output .= "const __OHCRUD_CMS_LAZY_LOAD_IMAGES__ = " . (__OHCRUD_CMS_LAZY_LOAD_IMAGES__ ? 'true' : 'false') . ";\n";
        $output .= "document.querySelector('.cmsLogin') ? document.querySelector('.cmsLogin').textContent = '" . ($this->loggedIn == true ? 'LOGOUT' : 'LOGIN') . "' : null;\n";
        $output .= "</script>\n";

        return $output;
    }

    // Add lazy loading to all images in the content
    private function addLazyLoadingToImages(string $html) {
        // The regular expression pattern to find all <img> tags.
        $pattern = '/<img(.*?)>/is';

        // preg_replace_callback processes each match with a custom function.
        return preg_replace_callback($pattern, function ($matches) {
            // $matches[0] is the full matched <img> tag string.
            // $matches[1] is the captured content (the attributes).
            $tag_content = $matches[1];

            // Check if 'loading="lazy"' is already present.
            if (preg_match('/loading\s*=\s*(["\']?)lazy\1/i', $tag_content)) {
                // The loading attribute is already set to 'lazy', so return the original tag.
                return $matches[0];
            }

            // If the tag already has a 'loading' attribute, but it's not 'lazy',
            // we'll just return the original tag to avoid overriding it.
            // This is a safety check for cases like loading="eager" or loading="auto".
            if (preg_match('/loading\s*=\s*(["\']?).*?\1/i', $tag_content)) {
                return $matches[0];
            }

            // At this point, the tag does not have a loading attribute.
            $modified_tag = $matches[0];

            // Add 'loading="lazy"' to the tag just before the closing bracket.
            $modified_tag = preg_replace('/>$/', ' loading="lazy">', $modified_tag);

            return $modified_tag;
        }, $html);
    }

    // Get footer
    private function getFooter() {
        $footer = '';
        $footer .= '<p><a href="/" class="external">HOME</a>';
        $footer .= ' | ';
        $footer .= 'CMS powered by <a href="https://github.com/fellowgeek/ohcrud" class="external">ohCRUD!</a> - Copyright &copy; ' . date('Y') . ' ' . $this->escapeHTML(__SITE__) ;
        $footer .= ' | ';
        $footer .= 'Generated in ' . round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 4) . ' second(s). - PHP ' . PHP_VERSION;
        $footer .= ' | ';
        $loginUrl = '/login/';
        if ($this->path !== '/login/') {
            $loginUrl .= '?redirect=' . urlencode($this->path);
        }
        $footer .= '<a href="' . $loginUrl . '" class="external cmsLogin"></a></p>';
        return $footer;
    }

    // Process theme
    private function processTheme() {

        $output = '';
        $path = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $this->path);

        // Fallback to default theme ans layout if file does not exist
        if (file_exists(__SELF__ . 'themes/' . $this->theme . '/' . $this->layout . '.html') == false) {
            $this->theme = __OHCRUD_CMS_DEFAULT_THEME__;
            $this->layout = __OHCRUD_CMS_DEFAULT_LAYOUT__;
        }

        // Handle admin themes and layouts
        if ($this->isAdmin == true && $this->actionMode !== false) {
            switch ($this->actionMode) {
                case 'edit':
                    $this->theme = __OHCRUD_CMS_ADMIN_THEME__;
                    $this->layout = 'edit';
                    break;
                case 'tables':
                case 'files':
                    $this->theme = __OHCRUD_CMS_ADMIN_THEME__;
                    $this->layout = 'tables';
                    break;
                case 'sql':
                    $this->theme = __OHCRUD_CMS_ADMIN_THEME__;
                    $this->layout = 'sql';
                    break;
                case 'sql':
                    $this->theme = __OHCRUD_CMS_ADMIN_THEME__;
                    $this->layout = 'server';
                    break;
                case 'logs':
                    $this->theme = __OHCRUD_CMS_ADMIN_THEME__;
                    $this->layout = 'logs';
                    break;
                default:
                    $this->theme = __OHCRUD_CMS_ADMIN_THEME__;
                    $this->layout = __OHCRUD_CMS_ADMIN_LAYOUT__;
            }
        }

        // Resolve the final template beneath themes, including symlink targets.
        $themeRoot = realpath(__SELF__ . 'themes');
        $themePath = realpath(__SELF__ . 'themes/' . $this->theme . '/' . $this->layout . '.html');
        if (preg_match('/^[a-zA-Z0-9_-]+$/D', $this->theme) !== 1 ||
            preg_match('/^[a-zA-Z0-9_-]+$/D', $this->layout) !== 1 ||
            $themeRoot === false || $themePath === false ||
            strpos($themePath, $themeRoot . DIRECTORY_SEPARATOR) !== 0) {
            $this->theme = __OHCRUD_CMS_DEFAULT_THEME__;
            $this->layout = __OHCRUD_CMS_DEFAULT_LAYOUT__;
            $themePath = __SELF__ . 'themes/' . $this->theme . '/' . $this->layout . '.html';
        }

        // Load theme and layout.
        ob_start();
        include $themePath;
        $output = ob_get_clean();

        // Process embedded content in the theme
        $themeContent = new \app\models\mContent;
        $themeContent->text = $output;
        $themeContent->html = $output;
        $themeContent = $this->processContent($themeContent);

        // Set HTML output
        $output = $themeContent->html;

        // Process theme (fix the path of all relative href and src attributes, add content, title, stylesheet, javascript, etc...)
        $output = preg_replace("@(<script|<link|<use)(.*?)href=\"(?!(http://)|(\[)|(https://))/?(.*?)\"@i", "$1$2href=\"" . "/themes/". $this->theme. "/$6\"", $output);
        $output = preg_replace("@(<script|<link|<img)(.*?)src=\"(?!(http://)|(\[)|(https://))/?(.*?)\"@i", "$1$2src=\"" . "/themes/". $this->theme. "/$6\"", $output);

        if (__OHCRUD_CMS_MINIFY_CSS__ == true) {
            // Minify CSS files in the theme
            $output = preg_replace_callback(
                "@<link(.*?)href=\"(?!(http://)|(https://)|(\[)|(data:))/?(.*?\.css[^\"]*)\"(.*?)>@i",
                function ($matches) {
                    if (isset($matches[6]) == true) {
                        $originalPath = '/' . $matches[6];
                        $this->includeCSSFile($originalPath, 10);
                    }
                    // remove the HTML tag
                    return '';
                },
                $output
            );
        }

        if (__OHCRUD_CMS_MINIFY_JS__ == true) {
            // Minify JS files in the theme
            $output = preg_replace_callback(
                "@<script(.*?)src=\"(?!(http://)|(https://)|(\[)|(data:))/?(.*?\.js[^\"]*)\"(.*?)>(.*?)</script>@i",
                function ($matches) {
                    if (isset($matches[6]) == true) {
                        $originalPath = '/' . $matches[6];
                        $this->includeJSFile($originalPath, 10);
                    }
                    // remove the HTML tag
                    return '';
                },
                $output
            );
        }

        // Gather and process CSS and JS assets
        $this->getMetaTags();
        $this->getCSSAssets();
        $this->getJSAssets();

        if ($this->actionMode == true) {
            $output = str_ireplace('{{CMS:CONTENT}}', $this->getAdminView($this->actionMode)->html, $output);
            $output = str_ireplace('{{CMS:THEME}}', $this->escapeHTML($this->content->theme), $output);
            $output = str_ireplace('{{CMS:LAYOUT}}', $this->escapeHTML($this->content->layout), $output);
            $output = str_ireplace('{{CMS:STATUS}}', $this->content->status, $output);
            $output = str_ireplace('{{CMS-IS-DELETED}}', $this->content->isDeleted, $output);
        }

        // Replace ohCRUD core content templates with the proccessed content from the cms
        $output = str_ireplace("{{CMS:CONTENT}}", $this->content->html . "{{CMS:UNCACHABLE-HTML}}", $output);
        $output = str_ireplace("{{CMS:CONTENT-TEXT}}", htmlspecialchars($this->content->text, ENT_QUOTES, 'UTF-8'), $output);

        // Replace ohCRUD templates with the proccessed content from the cms
        $output = str_ireplace("{{CMS:APP}}", __APP__, $output);
        $output = str_ireplace("{{CMS:SITE}}", $this->escapeHTML(__SITE__), $output);
        $output = str_ireplace("{{CMS:DOMAIN}}", $this->escapeHTML(__DOMAIN__), $output);
        $output = str_ireplace("{{CMS:SUB_DOMAIN}}", $this->escapeHTML(__SUB_DOMAIN__), $output);
        $output = str_ireplace("{{CMS:PATH}}", $path, $output);
        $output = str_ireplace("{{CMS:TITLE}}", htmlspecialchars($this->content->title, ENT_QUOTES, 'UTF-8'), $output);
        $output = str_ireplace("{{CMS:META}}", $this->content->metaTags, $output);
        $output = str_ireplace("{{CMS:STYLESHEET}}", $this->content->stylesheet, $output);

        // Include javascript assets and uncachable javascript constants
        $output = str_ireplace("{{CMS:JAVASCRIPT}}", "{{CMS:UNCACHABLE-JS}}" . $this->content->javascript, $output);

        // Include ohCRUD footer into the template
        $output = str_ireplace("{{CMS:OHCRUD}}", $this->getFooter(), $output);

        // Version
        $output = str_ireplace("{{CMS:VERSION}}", $this->version, $output);

        // Add lazy loading to all images in the content
        if (__OHCRUD_CMS_LAZY_LOAD_IMAGES__ == true) {
            $output = $this->addLazyLoadingToImages($output);
        }

        $this->data = $output;
    }

    // Scan components directory and return a list of component paths
    // Load admin panel views
    private function getAdminView($viewName) {
        $content = new \app\models\mContent;
        $viewPath = 'app/views/admin/' . $viewName . '.phtml';
        $fullPath = __SELF__ . $viewPath;

        // Security check
        $baseDir = realpath(__SELF__ . 'app/views/admin');
        $realFullPath = realpath($fullPath);

        if ($realFullPath === false || ($baseDir === false || strpos($realFullPath, $baseDir . DIRECTORY_SEPARATOR) !== 0)) {
            $this->log('warn', 'Admin view Local File Inclusion (LFI) attempt blocked', ['view' => $viewName]);
            $content->html = '<p>Admin view not found.</p>';
            return $content;
        }

        ob_start();
        include($realFullPath);
        $content->html = ob_get_clean();
        return $content;
    }

    private function scanComponents($dir) {
        $components = [];
        try {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($files as $file) {
                if ($file->isDir()){
                    continue;
                }
                if ($file->getExtension() == 'php') {
                    $componentPath = str_replace(__SELF__ . 'app/components/', '', $file->getPathname());
                    $componentPath = str_replace('.php', '', $componentPath);
                    $components[] = $componentPath;
                }
            }
        } catch(\Exception $e) {
            $this->log('error', 'Failed to scan components directory.', ['error' => $e->getMessage()]);
        }
        return $components;
    }

    // Check if the requested path is a hard-coded file
    private function isHardCoded($path) {
        $path = trim($path ?? '', '/');
        if (empty($path)) {
            return false;
        }
        $fullPath = __SELF__ . 'app/views/cms/' . $path . '.phtml';

        $baseDir = realpath(__SELF__ . 'app/views/cms');
        $realFullPath = realpath($fullPath);

        if ($realFullPath !== false && $baseDir !== false && strpos($realFullPath, $baseDir . DIRECTORY_SEPARATOR) === 0 && file_exists($realFullPath)) {
            return true;
        }

        return false;
    }

}
