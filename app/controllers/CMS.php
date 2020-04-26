<?php
namespace App\Controllers;

use Parsedown;
use HTMLPurifier;

// prevent direct access
if(isset($GLOBALS['OHCRUD']) == false) { die(); }

class CMS extends \OhCrud\DB {

    public $path;
    public $content;
    public $theme = 'default.php';
    public $jsFiles = [];
    public $cssFiles = [];

    public function __construct() {

        parent::__construct();

        $this->request = $_REQUEST;
        $this->content = new \App\Models\Content;

        // setup markdown processor and HTML purifier
        $this->parsedown = new Parsedown();
        $this->purifier = new HTMLPurifier();
        $this->purifier->config->set('HTML.SafeIframe', true);
        $this->purifier->config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

    }

    // handler for all incoming requests
    public function defaultPathHandler($path, $pathArray) {

        $this->path = \strtolower($path);

        // add assets for page editor if needed
        if(isset($_SESSION['User']) == true && isset($this->request['action']) == true && $this->request['action'] == 'edit') {
            // CSS
            $this->includeCSSFile('font-awesome.min.css', 1);
            $this->includeCSSFile('simplemde.min.css', 2);
            // Javascript
            $this->includeJSFile('simplemde.min.js', 1);
            $this->includeJSFile('editor.js', 2);
        }

        $this->content = $this->getContent($path);
        $this->processContent();
        $this->processWidgets();
        $this->getCSSAssets();
        $this->getJSAssets();

        ob_start();
        include __SELF__ . 'app/views/cms/theme/' . $this->theme;
		$this->data = ob_get_clean();

        $this->outputType = 'HTML';
        $this->output();

    }

    // include CSS file(s)
    public function includeCSSFile($file, $priority = 100) {

        if(isset($this->cssFiles[$file]) == false) {
            $this->cssFiles[$file] = $priority;
            asort($this->cssFiles);
        }

    }

    // get CSS assets
    private function getCSSAssets() {

        $this->content->stylesheet = '';
        foreach($this->cssFiles as $cssFile => $priority) {
            $this->content->stylesheet .= '<link rel="stylesheet" href="/assets/css/' . $cssFile . '" media="all" />' . "\n";
        }

    }

    // include Javascript file(s)
    public function includeJSFile($file, $priority = 100) {

        if(isset($this->jsFiles[$file]) == false) {
            $this->jsFiles[$file] = $priority;
            asort($this->jsFiles);
        }

    }

    // get Javascript assets
    private function getJSAssets() {

        $this->content->javascript = '';
        foreach($this->jsFiles as $jsFile => $priority) {
            $this->content->javascript .= '<script src="/assets/js/' . $jsFile . '"></script>' . "\n";
        }

    }

    // load content
    private function getContent($path, $shouldSetOutputStatusCode = true) {

        $content = new \App\Models\Content;
        $content->theme = $this->content->theme;

        // try getting page content from file
        if(\file_exists(__SELF__ . 'app/views/cms/' . trim($path, '/') . '.phtml') == true) {
            $content = $this->getContentFromFile($path);
            return $content;
        }

        // try getting page content from database
        $page = $this->read(
            'Pages',
            'URL = :URL AND STATUS = ' . \App\Models\Pages::STATUS_ACTIVE,
            [
                ':URL' =>  $path
            ]
        )->first();

        // check if page exists
        if($page == false) {
            if($shouldSetOutputStatusCode) $this->outputStatusCode = 404;

            $content->title = trim(ucwords(str_replace('/', ' ', $path)));
            if(($this->request['action'] ?? '') != 'edit') {
                $content = $this->getContentFromFile($path, true);
            }
            $content->is404 = true;
            return $content;
        }

        $content->type = \App\Models\Content::TYPE_DB;
        $content->title = $page->NAME;

        // check if user has permission
        $userPermissions = (isset($_SESSION['User']->PERMISSIONS) == true) ? $_SESSION['User']->PERMISSIONS : false;
        if($page->PERMISSIONS == __OHCRUD_PERMISSION_ALL__ || ($page->PERMISSIONS >= $userPermissions && $userPermissions != false)) {
            $content->text = $page->TEXT;
            $content->html = $this->purifier->purify($this->parsedown->text($page->TEXT));
        } else {
            $content->html = '<mark>Oh CRUD! You are not allowed to see this.</mark>';;
        }

        return $content;

    }

    // load widget(s)
    private function getWidget($widgetString, $shouldSetOutputStatusCode = true) {

        $content = new \App\Models\Content;
        $widgetParameters = [];

        parse_str(str_replace('|', '&', $widgetString), $widgetParameters);
        reset($widgetParameters);
        $widgetClass = key($widgetParameters);

        // check if widget exists
        if(\file_exists(__SELF__ . 'app/controllers/widgets/' . $widgetClass . '.php') == true) {

            $widgetClass = 'App\Controllers\Widgets\\' . $widgetClass;
            $widget = new $widgetClass;
            $widget->output($widgetParameters);
            $content = $widget->content;

            if(($this->request['action'] ?? '') != 'edit') {
                // get widget CSS assets
                foreach($widget->cssFiles as $cssFile => $priority) {
                    $this->includeCSSFile($cssFile, $priority);
                }
                // get widget Javascript assets
                foreach($widget->jsFiles as $jsFile => $priority) {
                    $this->includeJSFile($jsFile, $priority);
                }
            }

        } else {
            $content->html = '<mark>Oh CRUD! Widget not found.</mark>';
        }

        return $content;

    }

    // load hard coded content
    private function getContentFromFile($path, $is404 = false) {

        $content = new \App\Models\Content;
        $content->theme = $this->content->theme;
        $content->type = \App\Models\Content::TYPE_FILE;

        $content->title = ucwords(trim($path, '/'));
        ob_start();
        include(__SELF__ . 'app/views/cms/' . trim(($is404 ? '404' : $path), '/') . '.phtml');
        $content->text = ob_get_clean();
        $content->html = $content->text;

        return $content;

    }

    // process embedded content
    private function processContent() {

        $matches = [];
        preg_match_all('/{{(.*?)}}/i', $this->content->text, $matches);

        if(isset($matches[1]) == true) {
            foreach($matches[1] as $match) {
                $embeddedContent = $this->getContent('/' . $match . '/', false);

                if($embeddedContent->is404 == true) continue;
                $this->content->html = str_ireplace('{{' . $match . '}}', $embeddedContent->html, $this->content->html);

                // do not replace 'text' property if we are in edit mode
                if(($this->request['action'] ?? '') != 'edit') {
                    $this->content->text = str_ireplace('{{' . $match . '}}', $embeddedContent->text, $this->content->text);
                }
            }
        }

    }

    // process widget(s)
    private function processWidgets() {

        $matches = [];
        preg_match_all('/\[\[(.*?)\]\]/i', $this->content->text, $matches);

        if(isset($matches[1]) == true) {
            foreach($matches[1] as $match) {
                $embeddedContent = $this->getWidget(preg_replace('/\[|\]/', "", $match), false);
                if($embeddedContent->is404 == true) continue;
                $this->content->html = str_ireplace('[[' . $match . ']]', $embeddedContent->html, $this->content->html);
                // do not replace 'text' property if we are in edit mode
                if(($this->request['action'] ?? '') != 'edit') {
                    $this->content->text = str_ireplace('[[' . $match . ']]', $embeddedContent->text, $this->content->text);
                }
            }
        }

    }

}
