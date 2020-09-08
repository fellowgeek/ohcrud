<?php
namespace app\controllers;

use Parsedown;
use HTMLPurifier;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class CMS extends \OhCrud\DB {

    public $path;
    public $content;
    public $theme = __OHCRUD_CMS_DEFAULT_THEME__;
    public $layout = __OHCRUD_CMS_DEFAULT_LAYOUT__;
    public $jsFiles = [];
    public $cssFiles = [];
    public $editMode = false;
    public $loggedIn = false;

    public function __construct() {

        parent::__construct();

        $this->request = $_REQUEST;
        $this->content = new \app\models\Content;
        $this->pages = new \app\models\Pages;

        // set login status
        $this->loggedIn = isset($_SESSION['User']);
        // set edit mode
        if ($this->loggedIn == true && isset($this->request['action']) == true && $this->request['action'] == 'edit') {
            $this->editMode = true;
        }

        // setup markdown processor and HTML purifier
        $this->parsedown = new Parsedown();
        $this->purifier = new HTMLPurifier();
        $this->purifier->config->set('HTML.SafeIframe', true);
        $this->purifier->config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

    }

    // handler for all incoming requests
    public function defaultPathHandler($path, $pathArray) {

        $this->outputType = 'HTML';

        // normalize path
        $this->path = \strtolower($path);

        // get cache
        $cachedResponse = $this->getCache(__CLASS__ . __FUNCTION__ . $this->path, 3600);
        if ($this->loggedIn == false && $cachedResponse != false) {
            $this->data = $cachedResponse;
            $this->output();
            return;
        } else {
            $this->unsetCache(__CLASS__ . __FUNCTION__ . $this->path);
        }

        // include application javascript & css files
        $this->includeJSFile('application.js', 1);
        $this->includeCSSFile('styles.css', 2);

        // add assets for page editor if needed
        if ($this->editMode == true) {
            $this->includeCSSFile('simplemde.min.css', 1);
            $this->includeJSFile('simplemde.min.js', 2);
            $this->includeJSFile('editor.js', 3);
        }

        // get content and set theme & layout form content
        $this->content = $this->getContent($this->path);
        $this->theme = $this->content->theme;
        $this->layout = $this->content->layout;

        // process embeded content & widgets
        $this->content = $this->processContent($this->content);
        $this->content = $this->processWidgets($this->content);
        $this->getCSSAssets();
        $this->getJSAssets();

        // process theme & layout
        $this->processTheme();

        // set cache
        if ($this->editMode == false) {
            $this->setCache(__CLASS__ . __FUNCTION__ . $this->path, $this->data);
        }

        $this->outputType = 'HTML';
        $this->output();

    }

    // include CSS file(s)
    public function includeCSSFile($file, $priority = 100) {

        if (isset($this->cssFiles[$file]) == false) {
            $this->cssFiles[$file] = $priority;
            asort($this->cssFiles);
        }

    }

    // get themes and layouts
    private function getThemes() {
        $scan = glob('themes/*/*.html');

        $themes = [];
        foreach($scan as $layoutFile) {
            $matches = [];
            preg_match('/themes\/(.*?)\/(.*?)\.html/', $layoutFile, $matches);
            if(isset($matches[1]) == true) {
                $theme = $matches[1];
            }

            $matches = [];
            preg_match('/themes\/(.*?)\/(.*?)\.html/', $layoutFile, $matches);
            if(isset($matches[2]) == true) {
                $layout = $matches[2];
            }

            $themes[$theme][] = $layout;
        }

        return \base64_encode(json_encode($themes));
    }

    // get CSS assets
    private function getCSSAssets() {

        $this->content->stylesheet = '';
        foreach($this->cssFiles as $cssFile => $priority) {
            $this->content->stylesheet .= '<link rel="stylesheet" href="/global-assets/css/' . $cssFile . '" media="all" />' . "\n";
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
        foreach($this->jsFiles as $jsFile => $priority) {
            $this->content->javascript .= '<script src="/global-assets/js/' . $jsFile . '"></script>' . "\n";
        }

    }

    // load content
    private function getContent($path, $shouldSetOutputStatusCode = true) {

        $content = new \app\models\Content;

        // try getting page content from file
        if (\file_exists(__SELF__ . 'app/views/cms/' . trim($path, '/') . '.phtml') == true || $path == '/login/') {
            $content = $this->getContentFromFile($path);
            return $content;
        }

        // try getting page content from database
        $page = $this->read(
            'Pages',
            'URL = :URL',
            [
                ':URL' =>  $path
            ]
        )->first();

        // check if page does not exists
        if ($page == false || $page->STATUS != \app\models\Pages::STATUS_ACTIVE) {
            if ($shouldSetOutputStatusCode) $this->outputStatusCode = 404;

            $content->title = trim(ucwords(str_replace('/', ' ', $path)));
            if (($this->request['action'] ?? '') != 'edit') {
                $content = $this->getContentFromFile($path, true);
            }
            if (($page->STATUS ?? -1) == \app\models\Pages::STATUS_INACTIVE) {
                $content->isDeleted = true;
            }
            $content->is404 = true;
            return $content;
        }

        // get page content from database
        $content->type = \app\models\Content::TYPE_DB;
        $content->title = $page->TITLE;
        $content->theme = $page->THEME;
        $content->layout = $page->LAYOUT;

        // check if user has permission
        $userPermissions = (isset($_SESSION['User']->PERMISSIONS) == true) ? $_SESSION['User']->PERMISSIONS : false;
        if ($page->PERMISSIONS == __OHCRUD_PERMISSION_ALL__ || ($page->PERMISSIONS >= $userPermissions && $userPermissions != false)) {
            $content->text = $page->TEXT;
            $content->html = $this->purifier->purify($this->parsedown->text($page->TEXT));
        } else {
            $content->html = '<mark>Oh CRUD! You are not allowed to see this.</mark>';
        }

        return $content;

    }

    // load widget(s)
    private function getWidget($widgetString, $shouldSetOutputStatusCode = true) {

        $content = new \app\models\Content;
        $widgetParameters = [];

        parse_str(str_replace('|', '&', $widgetString), $widgetParameters);
        $widgetClass = key($widgetParameters);
        array_shift($widgetParameters);

        // check if widget exists
        if (\file_exists(__SELF__ . 'app/controllers/widgets/' . $widgetClass . '.php') == true) {

            $widgetClass = 'app\controllers\Widgets\\' . $widgetClass;
            $widget = new $widgetClass;
            $widget->output($widgetParameters);
            $content = $widget->content;

            // get widget CSS assets
            foreach($widget->cssFiles as $cssFile => $priority) {
                $this->includeCSSFile($cssFile, $priority);
            }
            // get widget Javascript assets
            foreach($widget->jsFiles as $jsFile => $priority) {
                $this->includeJSFile($jsFile, $priority);
            }

        } else {
            $content->html = '<mark>Oh CRUD! Widget not found.</mark>';
        }

        return $content;

    }

    // load hard coded content
    private function getContentFromFile($path, $is404 = false, $isSystem = false) {

        if ($is404 == true) $isSystem = true;
        if ($path == '/login/') $isSystem = true;

        $content = new \app\models\Content;
        $content->type = \app\models\Content::TYPE_FILE;

        $content->title = ucwords(trim($path, '/'));
        ob_start();
        include(__SELF__ . 'app/views/cms/' . ($isSystem ? 'system/' : '') . trim(($is404 ? '404' : $path), '/') . '.phtml');

        $content->text = ob_get_clean();
        $content->html = $content->text;

        return $content;

    }

    // process theme
    private function processTheme() {

        $output = '';

        // fallback to default theme ans layout if file does not exist
        if (\file_exists(__SELF__ . 'themes/' . $this->theme . '/' . $this->layout . '.html') == false || $this->editMode == true) {
            $this->theme = __OHCRUD_CMS_DEFAULT_THEME__;
            $this->layout = __OHCRUD_CMS_DEFAULT_LAYOUT__;
        }

        // load theme and layout
        ob_start();
        include __SELF__ . 'themes/' . $this->theme . '/' . $this->layout . '.html';
        $output = ob_get_clean();

        // process embeded content in theme
        $themeContent = new \app\models\Content;
        $themeContent->text = $output;
        $themeContent->html = $output;
        $themeContent = $this->processContent($themeContent);
        $themeContent = $this->processWidgets($themeContent);
        $output = $themeContent->html;

        // process theme (fix the path of all relative href and src attributes, add content, title, stylesheet, javascript, etc...)
        $editIconHTML = ($this->loggedIn && $this->content->type == \app\models\Content::TYPE_DB) ? '<div id="ohcrud-editor-edit" data-url="' . $this->path . '?action=edit"></div>' . "\n" : '';

        $output = preg_replace("@(script|link)(.*?)href=\"(?!(http://)|(\[)|(https://))/?(.*?)\"@i", "$1$2href=\"" . "/themes/". $this->theme. "/$6\"", $output);
        $output = preg_replace("@(script|link|img)(.*?)src=\"(?!(http://)|(\[)|(https://))/?(.*?)\"@i", "$1$2src=\"" . "/themes/". $this->theme. "/$6\"", $output);

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
        $output = str_ireplace("{{CMS:JAVASCRIPT}}",        $this->content->javascript, $output);
        $output = str_ireplace("{{CMS:OHCRUD}}",            '<p>Oh CRUD! by <a href="https://erfan.me">ERFAN REED</a> - Copyright &copy; ' . date('Y') . ' - All rights reserved. Page generated in ' . round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 3) . ' second(s). | <a href="/login/">LOGIN</a></p>', $output);

        $this->data = $output;

    }

    // process embedded content
    private function processContent($content) {

        // skip processing when in edit mode
        if ($this->editMode == true) {
            return $content;
        }

        $matches = [];
        preg_match_all('/{{(.*?)}}/i', $content->text, $matches);

        if (isset($matches[1]) == true) {
            foreach($matches[1] as $match) {
                $embeddedContent = $this->getContent('/' . $match . '/', false);

                if ($embeddedContent->is404 == true) continue;
                $content->html = str_ireplace('{{' . $match . '}}', $embeddedContent->html, $content->html);
                $content->text = str_ireplace('{{' . $match . '}}', $embeddedContent->text, $content->text);
            }
        }

        return $content;

    }

    // process widget(s)
    private function processWidgets($content) {

        // skip processing when in edit mode
        if ($this->editMode == true) {
            return $content;
        }

        $matches = [];
        preg_match_all('/\[\[(.*?)\]\]/i', $content->text, $matches);

        if (isset($matches[1]) == true) {
            foreach($matches[1] as $match) {
                $embeddedContent = $this->getWidget($match, false);
                if ($embeddedContent->is404 == true) continue;
                $content->html = str_ireplace('[[' . $match . ']]', $embeddedContent->html, $content->html);
                $content->text = str_ireplace('[[' . $match . ']]', $embeddedContent->text, $content->text);
            }
        }

        return $content;

    }

}
