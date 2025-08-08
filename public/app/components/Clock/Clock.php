<?php
namespace app\components\clock;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

/*

  Clock Component

  This component is responsible for displaying the current time in form of an analog clock.

  This component serves as a simple demonstration of how to create a component in OhCRUD.

  Usage:
  [[Clock/Clock]]

  It can be used in pages, templates, or components to render a clock on the page.

*/

class Clock extends \app\components\Component {

    // Constructor for this class, which takes a $request parameter.
    public function __construct($request, $path) {
        parent::__construct($request, $path);
        // Set the 'directory' property to the relative path from this script's location to the document root.
        $this->directory = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])) . '/';
    }

    public function output($parameters = []) {

        // Set component parameters to be extraxted in the view
        $this->variables = $parameters;

        // Include component specific JS assets
        $this->includeJSFile($this->directory . 'js/script.js');

        // Component HTML content
        $this->content->html = $this->loadView($this->directory . 'views/ClockView.phtml');

    }

}
