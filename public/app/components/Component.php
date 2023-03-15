<?php
namespace app\components;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Component extends \OhCrud\DB {

    public $path;
    public $content;
    public $jsFiles = [];
    public $cssFiles = [];
    public $variables = [];

    public function __construct() {

        parent::__construct();

        $this->request = $_REQUEST;
        $this->content = new \app\models\mContent;

    }

    public function includeCSSFile($file, $priority = 100) {

        if (isset($this->cssFiles[$file]) == false) {
            $this->cssFiles[$file] = $priority;
            asort($this->cssFiles);
        }

    }

    public function includeJSFile($file, $priority = 100) {

        if (isset($this->jsFiles[$file]) == false) {
            $this->jsFiles[$file] = $priority;
            asort($this->jsFiles);
        }

    }

    public function loadView($fileName) {

        if (empty($this->variables) == false) {
            extract($this->variables);
        }

        ob_start();
        include(__SELF__ . 'app/views/' . $fileName);
        $output = \ob_get_clean();

        return $output;

    }

    public function output($parameters = []) {
    }

}
