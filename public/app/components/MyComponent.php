<?php
namespace app\components;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class MyComponent extends \app\components\Component {

    public function output($parameters = []) {

        // include component specific CSS and JS assets
        // $this->includeCSSFile('my-component.css');
        // $this->includeJSFile('my-component.js');

        // component HTML content should go here
        $this->content->html = $_SERVER['REMOTE_ADDR'];
    }

}
