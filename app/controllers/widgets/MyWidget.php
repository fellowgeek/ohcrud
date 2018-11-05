<?php
namespace App\Controllers\Widgets;

// prevent direct access
if(isset($GLOBALS['OHCRUD']) == false) { die(); }

class MyWidget extends \App\Controllers\Widget {

    public function __construct() {

        parent::__construct();

    }

    public function output($parameters = []) {

        // include widget specific CSS and JS assets
        // $this->includeCSSFile('my-widget.css');
        // $this->includeJSFile('my-widget.js');

        // widget HTML content should go here
        $this->content->html = $_SERVER['REMOTE_ADDR'];

    }

}
?>