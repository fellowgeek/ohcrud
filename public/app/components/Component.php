<?php
namespace app\components;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Component extends \OhCrud\DB {

    public $path;
    public $content;
    public $jsFiles = [];
    public $cssFiles = [];
    public $variables = [];
    public $request;
    public $directory;

    // Constructor for the 'Component' class, which takes a $request parameter.
    public function __construct($request, $path = null) {
        parent::__construct();

        // Assign the provided $request to the 'request' property.
        $this->request = $request;
        // Assign the current CMS path to 'path' property
        $this->path = $path;
        // Set the 'directory' property to the relative path from this script's location to the document root.
        $this->directory = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])) . '/';
        // Define 'content' property
        $this->content = new \app\models\mContent;
    }

    // Method to include a CSS file with an optional priority.
    public function includeCSSFile($file, $priority = 100) {
        if (isset($this->cssFiles[$file]) == false) {
            $this->cssFiles[$file] = $priority;
            asort($this->cssFiles);
        }
    }

    // Method to include a JavaScript (JS) file with an optional priority.
    public function includeJSFile($file, $priority = 100) {
        if (isset($this->jsFiles[$file]) == false) {
            $this->jsFiles[$file] = $priority;
            asort($this->jsFiles);
        }
    }

    // Method to load and render a view from a file.
    public function loadView($fileName) {
        $output = '';

        // Check if the file specified by 'fileName' exists.
        if (file_exists(__SELF__ . $fileName) == false) {
            return false;
        }

        // Extract variables for use in the view, if 'variables' is not empty.
        if (empty($this->variables) == false) {
            extract($this->variables);
        }

        // Start output buffering, include the view file, and capture the output.
        ob_start();
        include(__SELF__ . $fileName);
        $output = \ob_get_clean();

        return $output;
    }

}
