<?php
namespace app\controllers;

use Parsedown;
use HTMLPurifier;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cCMS - CMS controller used by the OhCRUD framework
class cCMS extends \OhCrud\Controller {

    // The path of the requested content.
    public $path;
    // The content of the page.
    public $content;
    // The theme of the page.
    public $theme = __OHCRUD_CMS_DEFAULT_THEME__;
    // The layout of the page.
    public $layout = __OHCRUD_CMS_DEFAULT_LAYOUT__;
    // JavaScript files to include.
    public $jsFiles = [];
    // CSS files to include.
    public $cssFiles = [];
    // Flag indicating whether the user is in edit mode.
    public $editMode = false;
    // Flag indicating whether the user is logged in.
    public $loggedIn = false;
    // Request data.
    public $request = [];
    // Instance for managing pages.
    public $pages;
    // Markdown processor.
    public $parsedown;
    // HTML purifier for security.
    public $purifier;

    public function __construct() {
        parent::__construct();

        $this->request = $_REQUEST;
        $this->content = new \app\models\mContent;
        $this->pages = new \app\models\mPages;

        // Set login status
        $this->loggedIn = isset($_SESSION['User']);
        // Set edit mode
        if ($this->loggedIn == true && isset($this->request['action']) == true && $this->request['action'] == 'edit') {
            $this->editMode = true;
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
        $cachedResponse = $this->getCache(__CLASS__ . __FUNCTION__ . $this->path, 3600);
        if ($this->loggedIn == false && $cachedResponse != false) {
            $this->data = $cachedResponse;
            $this->output();
            return;
        } else {
            $this->unsetCache(__CLASS__ . __FUNCTION__ . $this->path);
        }

        // include application javascript & css files
        $this->includeJSFile('/global-assets/js/application.js', 1);
        $this->includeCSSFile('/global-assets/css/styles.css', 2);

        // add assets for page editor if needed
        if ($this->editMode == true) {
            $this->includeCSSFile('/global-assets/css/simplemde.min.css', 1);
            $this->includeJSFile('/global-assets/js/simplemde.min.js', 2);
            $this->includeJSFile('/global-assets/js/editor.js', 3);
        }

        // Get content and set theme & layout from content
        $this->content = $this->getContent($this->path);
        $this->theme = $this->content->theme;
        $this->layout = $this->content->layout;

        // Process embedded content & components
        $this->content = $this->processContent($this->content);
        $this->content = $this->processComponents($this->content);

        // Process theme & layout
        $this->processTheme();

        // Set cache
        if ($this->editMode == false) {
            $this->setCache(__CLASS__ . __FUNCTION__ . $this->path, $this->data);
        }

        $this->output();

    }

    // include CSS file(s)
    public function includeCSSFile($file, $priority = 100) {
        if (isset($this->cssFiles[$file]) == false) {
            $this->cssFiles[$file] = $priority;
            asort($this->cssFiles);
        }
    }

    // get CSS assets
    private function getCSSAssets() {
        $this->content->stylesheet = '';
        foreach ($this->cssFiles as $cssFile => $priority) {
            $this->content->stylesheet .= '<link rel="stylesheet" href="' . $cssFile . '" media="all" />' . "\n";
        }
    }

    // include Javascript file(s)
    public function includeJSFile($file, $priority = 100) {
        if (isset($this->jsFiles[$file]) == false) {
            $this->jsFiles[$file] = $priority;
            asort($this->jsFiles);
        }
    }

    // get Javascript assets
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
        if (\file_exists(__SELF__ . 'app/views/cms/' . trim($path, '/') . '.phtml') == true || $path == '/login/') {
            $content = $this->getContentFromFile($path);
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

        // Check if page does not exist
        if ($page == false || $page->STATUS != \app\models\mPages::ACTIVE) {
            if ($shouldSetOutputStatusCode) $this->outputStatusCode = 404;

            $content->title = trim(ucwords(str_replace('/', ' ', $path)));
            if (($this->request['action'] ?? '') != 'edit') {
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

        // Check if the user has permission
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

            // Get component CSS assets
            foreach ($component->cssFiles as $cssFile => $priority) {
                $this->includeCSSFile($cssFile, $priority);
            }
            // Get component Javascript assets
            foreach ($component->jsFiles as $jsFile => $priority) {
                $this->includeJSFile($jsFile, $priority);
            }

        } else {
            $content->html = '<mark>Oh CRUD! Component not found.</mark>';
        }

        return $content;
    }

    // Load hard-coded content
    private function getContentFromFile($path, $is404 = false, $isSystem = false) {

        if ($is404 == true) $isSystem = true;
        if ($path == '/login/') {
            $isSystem = true;
            // Terminate user session
            $this->unsetSession('User');
            $this->unsetSession('tempUser');
        }

        $content = new \app\models\mContent;
        $content->type = \app\models\mContent::TYPE_FILE;

        $content->title = ucwords(trim($path, '/'));
        ob_start();
        include(__SELF__ . 'app/views/cms/' . ($isSystem ? 'system/' : '') . trim(($is404 ? '404' : $path), '/') . '.phtml');

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
        $javascriptGlobals = '';

        // fallback to default theme ans layout if file does not exist
        if (\file_exists(__SELF__ . 'themes/' . $this->theme . '/' . $this->layout . '.html') == false || $this->editMode == true) {
            $this->theme = __OHCRUD_CMS_DEFAULT_THEME__;
            $this->layout = __OHCRUD_CMS_DEFAULT_LAYOUT__;
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
        $themeContent = $this->processComponents($themeContent);

        // gather CSS and JS assets
        $this->getCSSAssets();
        $this->getJSAssets();

        // Set HTML output
        $output = $themeContent->html;

        // Process theme (fix the path of all relative href and src attributes, add content, title, stylesheet, javascript, etc...)
        $editIconHTML = ($this->loggedIn && $this->content->type == \app\models\mContent::TYPE_DB) ? '<div id="ohcrud-editor-edit" data-url="' . $this->path . '?action=edit"></div>' . "\n" : '';

        $output = preg_replace("@(<script|<link|<use)(.*?)href=\"(?!(http://)|(\[)|(https://))/?(.*?)\"@i", "$1$2href=\"" . "/themes/". $this->theme. "/$6\"", $output);
        $output = preg_replace("@(<script|<link|<img)(.*?)src=\"(?!(http://)|(\[)|(https://))/?(.*?)\"@i", "$1$2src=\"" . "/themes/". $this->theme. "/$6\"", $output);

        if ($this->editMode == true) {
            $output = str_ireplace('{{CMS:CONTENT}}',       $this->getContentFromFile('cms', false, true)->html, $output);
            $output = str_ireplace('{{CMS:THEMES}}',        $this->getThemes(), $output);
            $output = str_ireplace('{{CMS:THEME}}',         $this->content->theme, $output);
            $output = str_ireplace('{{CMS:LAYOUT}}',        $this->content->layout, $output);
            $output = str_ireplace('{{CMS-IS-DELETED}}',    $this->content->isDeleted, $output);
        }

        $output = str_ireplace("{{CMS:PATH}}",              $this->path, $output);
        $output = str_ireplace("{{CMS:TITLE}}",             $this->content->title, $output);
        $output = str_ireplace("{{CMS:CONTENT}}",           $this->content->html . $editIconHTML, $output);
        $output = str_ireplace("{{CMS:CONTENT-TEXT}}",      $this->content->text, $output);
        $output = str_ireplace("{{CMS:STYLESHEET}}",        $this->content->stylesheet, $output);

        $javascriptGlobals .= "<script>\n";
        $javascriptGlobals .= "const __SITE__ = '" . __SITE__ . "';\n";
        $javascriptGlobals .= "const __DOMAIN__ = '" . __DOMAIN__ . "';\n";
        $javascriptGlobals .= "const __SUB_DOMAIN__ = '" . __SUB_DOMAIN__ . "';\n";
        $javascriptGlobals .= "const __OHCRUD_BASE_API_ROUTE__ = '" . __OHCRUD_BASE_API_ROUTE__ . "';\n";
        $javascriptGlobals .= "const __OHCRUD_DEBUG_MODE__ = " . (__OHCRUD_DEBUG_MODE__ ? 'true' : 'false') . ";\n";
        $javascriptGlobals .= "const __CSRF__ = '" . $this->CSRF() . "';\n";
        $javascriptGlobals .= "</script>\n";
        $output = str_ireplace("{{CMS:JAVASCRIPT}}", $javascriptGlobals . $this->content->javascript, $output);

        $output = str_ireplace("{{CMS:OHCRUD}}",            '<p>Oh CRUD! by <a href="https://erfan.me">ERFAN REED</a> - Copyright &copy; ' . date('Y') . ' - All rights reserved. Page generated in ' . round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 3) . ' second(s). | <a href="/login/">LOGIN</a></p>', $output);

        $this->data = $output;

    }

    // Process embedded content
    private function processContent($content) {

        // Skip processing when in edit mode
        if ($this->editMode == true) {
            return $content;
        }

        $matches = [];
        preg_match_all('/{{(.*?)}}/i', $content->text, $matches);

        if (isset($matches[1]) == true) {
            foreach ($matches[1] as $match) {
                $embeddedContent = $this->getContent('/' . $match . '/', false);

                if ($embeddedContent->is404 == true) continue;
                $content->html = str_ireplace('{{' . $match . '}}', $embeddedContent->html, $content->html);
                $content->text = str_ireplace('{{' . $match . '}}', $embeddedContent->text, $content->text);
            }
        }

        return $content;

    }

    // Process component(s)
    private function processComponents($content) {

        // Skip processing when in edit mode
        if ($this->editMode == true) {
            return $content;
        }

        $matches = [];
        preg_match_all('/\[\[(.*?)\]\]/i', $content->text, $matches);

        if (isset($matches[1]) == true) {
            foreach ($matches[1] as $match) {
                $embeddedContent = $this->getComponent($match, false);
                if ($embeddedContent->is404 == true) continue;
                $content->html = str_ireplace('[[' . $match . ']]', $embeddedContent->html, $content->html);
                $content->text = str_ireplace('[[' . $match . ']]', $embeddedContent->text, $content->text);
            }
        }

        return $content;

    }

}
