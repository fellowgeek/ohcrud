<?php
namespace app\controllers;

use Parsedown;
use HTMLPurifier;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cCMS - CMS controller used by the OhCRUD framework
class cCMS extends \OhCrud\DB {

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
    // Flag indicating whether the user is in edit mode.
    public $editMode = false;
    // Flag indicating whether should use cache.
    public $useCache = true;
    // Flag indicating whether the user is logged in.
    public $loggedIn = false;
    // Request data.
    public object $request;
    // Instance for managing pages.
    public $pages;
    // Markdown processor.
    public $parsedown;
    // HTML purifier for security.
    public $purifier;
    // Recursive content counter.
    public $recursiveContentCounter = 0;
    // Max recursive content
    public $maxRecursiveContent = 7;

    public function __construct($request) {
        parent::__construct();

        $this->request = $request;
        $this->content = new \app\models\mContent;
        $this->pages = new \app\models\mPages;

        // Set login status
        $this->loggedIn = isset($_SESSION['User']);

        // Set edit mode
        if ($this->loggedIn == true && ($this->request->action ?? '') == 'edit') {
            $this->editMode = true;
            $this->useCache = false;
        }

        // Redirect to login page if not logged in
        if ($this->loggedIn == false && ($this->request->action ?? '') == 'edit') {
            $this->redirect('/login/?redirect=' . $GLOBALS['PATH'] . '?action=edit');
            return;
        }

        // Setup markdown processor and HTML purifier
        $this->parsedown = new Parsedown();
        $this->purifier = new HTMLPurifier();
        $this->purifier->config->set('HTML.SafeIframe', true);
        $this->purifier->config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');
    }

    // Handler for all incoming requests
    public function defaultPathHandler($path) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_HTML);

        // Normalize path
        $this->path = \strtolower($path);

        // Get cached response (if any)
        $cachedResponse = $this->getCache(__CLASS__ . __FUNCTION__ . $this->path . md5(json_encode($_GET ?? '')), 3600);
        if ($this->useCache == true && $cachedResponse != false) {
            $this->data = $cachedResponse;
            // Inject the uncachable content
            $this->data = str_ireplace("{{CMS:UNCACHABLE-JAVASCRIPT}}", $this->getUnCachableContent(), $this->data);
            // Output the HTML page
            $this->output();
            return;
        } else {
            $this->unsetCache(__CLASS__ . __FUNCTION__ . $this->path);
        }

        // Include application javascript & css files
        $this->includeCSSFile('/global/css/global.css', 2);
        $this->includeJSFile('/global/js/global.js', 1);

        // Add assets for page editor if needed
        if ($this->editMode == true) {
            $this->includeJSFile('/themes/admin/assets/js/editor.js', 3);
        }

        // Get content and set theme & layout from content
        $this->content = $this->getContent($this->path);
        $this->theme = $this->content->theme;
        $this->layout = $this->content->layout;

        // Process embedded content & components
        $this->content = $this->processContent($this->content);

        // Process theme & layout
        $this->processTheme();

        // Set cache
        if ($this->editMode == false) {
            $this->setCache(__CLASS__ . __FUNCTION__ . $this->path . md5(json_encode($_GET ?? '')), $this->data);
        }

        // Inject the uncachable content
        $this->data = str_ireplace("{{CMS:UNCACHABLE-JAVASCRIPT}}", $this->getUnCachableContent(), $this->data);

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
        foreach ($this->jsFiles as $jsFile => $priority) {
            $this->content->javascript .= '<script src="' . $jsFile . '"></script>' . "\n";
        }
    }

    // Load content
    private function getContent($path, $shouldSetOutputStatusCode = true) {

        $content = new \app\models\mContent;

        // Try getting page content from file
        if (\file_exists(__SELF__ . 'app/views/cms/' . trim($path, '/') . '.phtml') == true) {
            $content = $this->getContentFromFile($path);
            // Handle special paths
            if ($path == '/login/') {
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
        if ($page == false || $page->STATUS != \app\models\mPages::ACTIVE) {
            if ($shouldSetOutputStatusCode) $this->outputStatusCode = 404;

            $content->title = trim(ucwords(str_replace('/', ' ', $path)));
            if (($this->request->action ?? '') != 'edit') {
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
        $userPermissions = (isset($_SESSION['User']->PERMISSIONS) == true) ? $_SESSION['User']->PERMISSIONS : false;
        if ($page->PERMISSIONS == __OHCRUD_PERMISSION_ALL__ || ($page->PERMISSIONS >= $userPermissions && $userPermissions != false)) {
            $content->text = $page->TEXT;
            $content->html = $this->purifier->purify($this->parsedown->text($page->TEXT));
        } else {
            $content->html = '<mark>Oh CRUD! You are not allowed to see this.</mark>';
        }

        return $content;
    }

    // Load component(s)
    private function getComponent($componentString, $shouldSetOutputStatusCode = true) {

        $content = new \app\models\mContent;
        $componentParameters = [];

        parse_str(str_replace('|', '&', $componentString), $componentParameters);
        $componentClassFile = key($componentParameters);
        $componentClass = '\app\components\\' . str_replace('/', '\\', $componentClassFile);
        array_shift($componentParameters);

        // Check if the component exists
        if (\file_exists(__SELF__ . 'app/components/' . $componentClassFile . '.php') == true && class_exists($componentClass) == true) {

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

    // Load hard-coded content
    private function getContentFromFile($path, $is404 = false, $isSystem = false) {
        $content = new \app\models\mContent;
        $content->type = \app\models\mContent::TYPE_FILE;
        $content->title = ucwords(trim($path, '/'));
        ob_start();
        include(__SELF__ . 'app/views/cms/' . trim(($is404 ? '404' : $path), '/') . '.phtml');
        $content->text = ob_get_clean();
        $content->html = $content->text;

        return $content;
    }

    // Get themes and layouts
    private function getThemes() {
        $scan = glob('themes/*/*.html');

        $themes = [];
        foreach ($scan as $layoutFile) {
            $matches = [];
            $theme = '';
            $layout = '';
            preg_match('/themes\/(.*?)\/(.*?)\.html/', $layoutFile, $matches);
            if (isset($matches[1]) == true) {
                $theme = $matches[1];
            }

            $matches = [];
            preg_match('/themes\/(.*?)\/(.*?)\.html/', $layoutFile, $matches);
            if (isset($matches[2]) == true) {
                $layout = $matches[2];
            }

            $themes[$theme][] = $layout;
        }

        return \base64_encode(json_encode($themes));
    }

    // Process theme
    private function processTheme() {

        $output = '';

        // Fallback to default theme ans layout if file does not exist
        if (\file_exists(__SELF__ . 'themes/' . $this->theme . '/' . $this->layout . '.html') == false) {
            $this->theme = __OHCRUD_CMS_DEFAULT_THEME__;
            $this->layout = __OHCRUD_CMS_DEFAULT_LAYOUT__;
        }

        if ($this->editMode == true) {
            $this->theme = __OHCRUD_CMS_ADMIN_THEME__;
            $this->layout = __OHCRUD_CMS_ADMIN_LAYOUT__;
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
        $editIconHTML = ($this->loggedIn && $this->content->type == \app\models\mContent::TYPE_DB) ? '<div id="btnCMSEdit" data-url="' . $this->path . '?action=edit"></div>' . "\n" : '';

        $output = preg_replace("@(<script|<link|<use)(.*?)href=\"(?!(http://)|(\[)|(https://))/?(.*?)\"@i", "$1$2href=\"" . "/themes/". $this->theme. "/$6\"", $output);
        $output = preg_replace("@(<script|<link|<img)(.*?)src=\"(?!(http://)|(\[)|(https://))/?(.*?)\"@i", "$1$2src=\"" . "/themes/". $this->theme. "/$6\"", $output);

        if ($this->editMode == true) {
            $output = str_ireplace('{{CMS:CONTENT}}',       $this->getContentFromFile('cms', false, true)->html, $output);
            $output = str_ireplace('{{CMS:THEMES}}',        $this->getThemes(), $output);
            $output = str_ireplace('{{CMS:THEME}}',         $this->content->theme, $output);
            $output = str_ireplace('{{CMS:LAYOUT}}',        $this->content->layout, $output);
            $output = str_ireplace('{{CMS-IS-DELETED}}',    $this->content->isDeleted, $output);
        }

        // Replace OhCRUD templates with the proccessed content from the cms
        $output = str_ireplace("{{CMS:PATH}}", $this->path, $output);
        $output = str_ireplace("{{CMS:TITLE}}", $this->content->title, $output);
        $output = str_ireplace("{{CMS:CONTENT}}", $this->content->html . $editIconHTML, $output);
        $output = str_ireplace("{{CMS:CONTENT-TEXT}}", $this->content->text, $output);
        $output = str_ireplace("{{CMS:META}}", $this->content->metaTags, $output);
        $output = str_ireplace("{{CMS:STYLESHEET}}", $this->content->stylesheet, $output);

        // Include javascript assets and uncachable javascript constants
        $output = str_ireplace("{{CMS:JAVASCRIPT}}", "{{CMS:UNCACHABLE-JAVASCRIPT}}" . $this->content->javascript, $output);
        // Include OhCRUD footer into the template
        $output = str_ireplace("{{CMS:OHCRUD}}", '<p><a href="/">HOME</a> | CMS powered by <a href="https://github.com/fellowgeek/ohcrud">Oh CRUD!</a> - Copyright &copy; ' . date('Y') . ' justshare.me | <a href="/privacy/">PRIVACY</a> | <a href="/login/">LOGIN</a></p>', $output);

        $this->data = $output;
    }

    private function getUnCachableContent() {

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
        $output .= "</script>\n";

        return $output;
    }

    // Process embedded content and components
    private function processContent($content) {
        // Skip processing when in edit mode
        if ($this->editMode == true) {
            return $content;
        }

        // Check for embedded content
        $regex = '/(?<=\{{)(?!CMS:META|CMS:TITLE|CMS:STYLESHEET|CMS:CONTENT|CMS:OHCRUD|CMS:JAVASCRIPT).*?(?=\}})/i';
        $matches = [];
        $matchCount = preg_match_all($regex, $content->html, $matches);

        // If embedded content is found, process it
        if ($matchCount > 0) {
            $this->recursiveContentCounter++;
            foreach ($matches[0] as $match) {
                if ($this->path == '/' . $match . '/' || $this->recursiveContentCounter > $this->maxRecursiveContent) {
                    $content->html = str_ireplace('{{' . $match . '}}', '<mark>Oh CRUD! Recursive content not allowed.</mark>', $content->html);
                    continue;
                }
                $embeddedContent = $this->getContent('/' . $match . '/', false);
                if ($embeddedContent->is404 == true) {
                    $content->html = str_ireplace('{{' . $match . '}}', '<mark>Oh CRUD! Content not found.</mark>', $content->html);
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
                    $content->html = str_ireplace('[[' . $match . ']]', '<mark>Oh CRUD! Recursive content not allowed.</mark>', $content->html);
                    continue;
                }
                $embeddedContent = $this->getComponent($match, false);
                if ($embeddedContent->is404 == true) {
                    $content->html = str_ireplace('[[' . $match . ']]', '<mark>Oh CRUD! Component not found.</mark>', $content->html);
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
        $regex = '/(?<=\{{)(?!CMS:META|CMS:TITLE|CMS:STYLESHEET|CMS:CONTENT|CMS:OHCRUD|CMS:JAVASCRIPT).*?(?=\}})/i';
        $matchCount += preg_match_all($regex, $text);
        $regex = '/(?<=\[\[)(.*?)(?=\]\])/i';
        $matchCount += preg_match_all($regex, $text);

        return $matchCount;
    }

}
