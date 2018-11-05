<?php
namespace App\Controllers;

// prevent direct access
if(isset($GLOBALS['OHCRUD']) == false) { die(); }

class Widget extends \OhCrud\DB {

    public $path;
    public $content;
    public $jsFiles = [];
    public $cssFiles = [];

    public function __construct() {

        parent::__construct();

        $this->request = $_REQUEST;
        $this->content = new \App\Models\Content;

    }

    public function includeCSSFile($file, $priority = 100) {

        if(isset($this->cssFiles[$file]) == false) {
            $this->cssFiles[$file] = $priority;
            asort($this->cssFiles);
        }

    }

    public function includeJSFile($file, $priority = 100) {

        if(isset($this->jsFiles[$file]) == false) {
            $this->jsFiles[$file] = $priority;
            asort($this->jsFiles);
        }

    }

    public function output($parameters = []) {
    }

}
?>