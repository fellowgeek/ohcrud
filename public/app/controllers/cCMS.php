<?php
namespace app\controllers;

use HTMLPurifier;
use Michelf\MarkdownExtra;
use MatthiasMullie\Minify;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cCMS - CMS controller used by the OhCRUD framework
class cCMS extends \ohCRUD\DB {

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
    public $allowedActions = ['edit', 'users', 'tables', 'files', 'logs', 'settings'];

    public function __construct($request) {
        parent::__construct();

        $this->request = $request;
        $this->content = new \app\models\mContent;
        $this->pages = new \app\models\mPages;

        // Set login status
        $this->loggedIn = isset($_SESSION['User']);

        // Set action mode
        if ($this->loggedIn == true && in_array($this->request->action ?? '', $this->allowedActions) == true) {
            $this->actionMode = $this->request->action;
            $this->useCache = false;
        }

        // Redirect to login page if not logged in
        if ($this->loggedIn == false && in_array($this->request->action ?? '', $this->allowedActions) == true) {
            $this->redirect('/login/?redirect=' . $GLOBALS['PATH'] . '?action=' . $this->request->action);
            return;
        }

        // Disable cache for login pages
        if ($GLOBALS['PATH'] === '/login/') {
            $this->useCache = false;
        }

        // Setup markdown processor
        $this->markdownExtra = new MarkdownExtra();
        $this->markdownExtra->enhanced_ordered_list = true;

        // Setup HTML purifier
        $this->purifier = new HTMLPurifier();
        $this->purifier->config->set('HTML.SafeIframe', true);
        $this->purifier->config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

        // Setup minifiers
        $this->minifierCSS = new Minify\CSS();
        $this->minifierJS = new Minify\JS();
    }

    // Handler for all incoming requests
    public function defaultPathHandler($path) {

        $this->setOutputType(\ohCRUD\Core::OUTPUT_HTML);

        // Normalize path
        $this->path = \strtolower($path);

        // Get cached response (if any)
        $cacheKey = 'cCMS:' . $this->path . http_build_query($_GET ?? '');
        $cachedResponse = $this->getCache($cacheKey, 3600);
        if ($this->useCache == true && $cachedResponse !== false) {
            $this->data = $cachedResponse;
            // Inject the uncachable content
            $this->data = str_ireplace("{{CMS:UNCACHABLE-HTML}}", $this->getUnCachableContentHTML(), $this->data);
            $this->data = str_ireplace("{{CMS:UNCACHABLE-JS}}", $this->getUnCachableContentJS(), $this->data);
            // Output the HTML page
            $this->output();
            return;
        }

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

        // Process theme & layout
        $this->processTheme();

        // Set cache
        if ($this->actionMode == false && $this->content->is404 == false) {
            $this->setCache($cacheKey, $this->data);
        }

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
            $this->content->metaTags .= '<meta name="' . $tag . '" content="' . $value . '">' . "\n";
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
            if (file_exists($basePathMinified . $combinedHash . '.min.css') == true) {
                // If the combined minified file already exists, use it
                $this->content->stylesheet .= '<link rel="stylesheet" href="/global/minified/' . $combinedHash . '.min.css" media="all" />' . "\n";
                return;
            }

            // If the combined minified file does not exist, create it
            foreach ($this->cssFiles as $cssFile => $priority) {
                $this->minifierCSS->add($basePath . $cssFile);
            }
            $this->minifierCSS->minify($basePathMinified . $combinedHash . '.min.css');

            // Use the combined minified file
            $this->content->stylesheet .= '<link rel="stylesheet" href="/global/minified/' . $combinedHash . '.min.css" media="all" />' . "\n";
            return;
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
            if (file_exists($basePathMinified . $combinedHash . '.min.js') == true) {
                // If the combined minified file already exists, use it
                $this->content->javascript .= '<script src="/global/minified/' . $combinedHash . '.min.js"></script>' . "\n";
                return;
            }

            // If the combined minified file does not exist, create it
            foreach ($this->jsFiles as $jsFile => $priority) {
                $this->minifierJS->add($basePath . $jsFile);
            }
            $this->minifierJS->minify($basePathMinified . $combinedHash . '.min.js');

            // Use the combined minified file
            $this->content->javascript .= '<script src="/global/minified/' . $combinedHash . '.min.js"></script>' . "\n";
            return;
        }

        foreach ($this->jsFiles as $jsFile => $priority) {
            $this->content->javascript .= '<script src="' . $jsFile . '"></script>' . "\n";
        }
    }

    // Get combined hash of all files
    private function getCombinedFilesHash($basePath, $files, $algo = 'sha1') {

        // Initialize the hashing context
        $ctx = hash_init($algo);

        // Loop through each file and update the hash context
        foreach ($files as $file) {
            if (is_readable($basePath. $file) == false) {
                return false;
            }
            // Stream the file directly into the hash function
            hash_update_file($ctx, $basePath. $file);
        }

        // Finalize the hash and return it
        return hash_final($ctx);
    }

    // Load content
    private function getContent($path, $shouldSetOutputStatusCode = true) {

        $content = new \app\models\mContent;

        // Try getting page content from file
        if (file_exists(__SELF__ . 'app/views/cms/' . trim($path, '/') . '.phtml') == true) {
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

        // Check if page does not exists
        if ($page === false || (int) $page->STATUS != $this::ACTIVE) {
            if ($shouldSetOutputStatusCode) $this->outputStatusCode = 404;

            $content->title = trim(ucwords(str_replace('/', ' ', $path)));
            if (($this->request->action ?? '') !== 'edit') {
                $content = $this->getContentFromFile($path, true);
            }
            if (($page->STATUS ?? -1) == $this::INACTIVE) {
                $content->isDeleted = true;
            }
            $content->is404 = true;
            return $content;
        }

        // Get page content from database
        $content->type = \app\models\mContent::TYPE_DB;
        $content->title = $page->TITLE;
        $content->theme = $page->THEME;
        $content->layout = $page->LAYOUT;

        // Check if user has permission
        $page->PERMISSIONS = (int) $page->PERMISSIONS;
        $userPermissions = (isset($_SESSION['User']->PERMISSIONS) == true) ? (int) $_SESSION['User']->PERMISSIONS : false;
        if ($page->PERMISSIONS == __OHCRUD_PERMISSION_ALL__ || ($page->PERMISSIONS >= $userPermissions && $userPermissions !== false)) {
            $content->text = $page->TEXT;
            // Process strikethrough text
            $page->TEXT = preg_replace('/~~(.*?)~~/i', '<del>$1</del>', $page->TEXT);
            // Convert Markdown to HTML
            $content->html = $this->purifier->purify($this->markdownExtra->transform($page->TEXT));
        } else {
            $content->html = '<mark>ohCRUD! You are not allowed to see this.</mark>';
        }

        return $content;
    }

    // Load hard-coded content
    private function getContentFromFile($path, $is404 = false) {
        $content = new \app\models\mContent;
        $content->type = \app\models\mContent::TYPE_FILE;
        $content->title = ucwords(trim($path, '/'));
        ob_start();
        include(__SELF__ . 'app/views/cms/' . trim(($is404 ? '404' : $path), '/') . '.phtml');
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
        $regex = '/(?<=\{{)(?!CMS:VERSION|CMS:META|CMS:TITLE|CMS:STYLESHEET|CMS:CONTENT|CMS:OHCRUD|CMS:JAVASCRIPT).*?(?=\}})/i';
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
                if ($embeddedContent->is404 == true) {
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
        $regex = '/(?<=\[\[)(.*?)(?=\]\])/i';
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
                if ($embeddedContent->is404 == true) {
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
        $regex = '/(?<=\{{)(?!CMS:VERSION|CMS:META|CMS:TITLE|CMS:STYLESHEET|CMS:CONTENT|CMS:OHCRUD|CMS:JAVASCRIPT).*?(?=\}})/i';
        $matchCount += preg_match_all($regex, $text);
        $regex = '/(?<=\[\[)(.*?)(?=\]\])/i';
        $matchCount += preg_match_all($regex, $text);

        return $matchCount;
    }

    // Load component(s)
    private function getComponent($componentString, $shouldSetOutputStatusCode = true) {

        $content = new \app\models\mContent;
        $componentParameters = $this->parseString($componentString);
        $componentClassFile = key($componentParameters);
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
            $content->is404 = true;
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

        if ($this->loggedIn == true) {
            $output.= '<div id="btnCMSEdit" data-url="' . $this->path . '?action=edit"></div>';
        }

        return $output;
    }

    // Get uncachable content JS
    private function getUnCachableContentJS() {
        $output = '';

        // Include Javascript constants and assets
        $output .= "<script>\n";
        $output .= "const __SITE__ = '" . __SITE__ . "';\n";
        $output .= "const __DOMAIN__ = '" . __DOMAIN__ . "';\n";
        $output .= "const __SUB_DOMAIN__ = '" . __SUB_DOMAIN__ . "';\n";
        $output .= "const __PATH__ = '" . $this->path . "';\n";
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
        $footer .= 'CMS powered by <a href="https://github.com/fellowgeek/ohcrud" class="external">ohCRUD!</a> - Copyright &copy; ' . date('Y') . ' ' . __SITE__ ;
        $footer .= ' | ';
        $footer .= 'Generated in ' . round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 3) . ' second(s). - PHP ' . PHP_VERSION;
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

        // Fallback to default theme ans layout if file does not exist
        if (file_exists(__SELF__ . 'themes/' . $this->theme . '/' . $this->layout . '.html') == false) {
            $this->theme = __OHCRUD_CMS_DEFAULT_THEME__;
            $this->layout = __OHCRUD_CMS_DEFAULT_LAYOUT__;
        }

        // Handle admin themes and layouts
        if ($this->loggedIn == true && isset($this->request->action) == true) {
            switch ($this->request->action) {
                case 'edit':
                    $this->theme = __OHCRUD_CMS_ADMIN_THEME__;
                    $this->layout = 'edit';
                    break;
                case 'files':
                case 'tables':
                    $this->theme = __OHCRUD_CMS_ADMIN_THEME__;
                    $this->layout = 'tables';
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

        // Load theme and layout
        ob_start();
        include __SELF__ . 'themes/' . $this->theme . '/' . $this->layout . '.html';
        $output = ob_get_clean();

        // Process embedded content in the theme
        $themeContent = new \app\models\mContent;
        $themeContent->text = $output;
        $themeContent->html = $output;
        $themeContent = $this->processContent($themeContent);

        // gather CSS and JS assets
        $this->getMetaTags();
        $this->getCSSAssets();
        $this->getJSAssets();

        // Set HTML output
        $output = $themeContent->html;

        // Process theme (fix the path of all relative href and src attributes, add content, title, stylesheet, javascript, etc...)
        $output = preg_replace("@(<script|<link|<use)(.*?)href=\"(?!(http://)|(\[)|(https://))/?(.*?)\"@i", "$1$2href=\"" . "/themes/". $this->theme. "/$6\"", $output);
        $output = preg_replace("@(<script|<link|<img)(.*?)src=\"(?!(http://)|(\[)|(https://))/?(.*?)\"@i", "$1$2src=\"" . "/themes/". $this->theme. "/$6\"", $output);

        if ($this->actionMode == true) {
            $output = str_ireplace('{{CMS:CONTENT}}', $this->getContentFromFile($this->actionMode, false, true)->html, $output);
            $output = str_ireplace('{{CMS:THEME}}', $this->content->theme, $output);
            $output = str_ireplace('{{CMS:LAYOUT}}', $this->content->layout, $output);
            $output = str_ireplace('{{CMS-IS-DELETED}}', $this->content->isDeleted, $output);
        }

        // Replace OhCRUD templates with the proccessed content from the cms
        $output = str_ireplace("{{CMS:PATH}}", $this->path, $output);
        $output = str_ireplace("{{CMS:TITLE}}", $this->content->title, $output);
        $output = str_ireplace("{{CMS:CONTENT}}", $this->content->html . "{{CMS:UNCACHABLE-HTML}}", $output);
        $output = str_ireplace("{{CMS:CONTENT-TEXT}}", $this->content->text, $output);
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

}
