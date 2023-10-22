<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cThemes - themes controller used by the CMS 
class cThemes extends \OhCrud\Controller {

    // Define permissions for the controller.    
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'getThemes' => 1,
    ];

    // Get themes and layouts from themes directory 
    public function getThemes() {
        
        // Set the output type for this controller to JSON
        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // Get a list of HTML files in the 'themes' directory and its subdirectories
        $scan = glob('themes/*/*.html');

        $themes = [];
        // Iterate through the list of HTML files
        foreach ($scan as $layoutFile) {
            $matches = [];
            // Use regular expressions to extract theme and layout information from the file path
            preg_match('/themes\/(.*?)\/(.*?)\.html/', $layoutFile, $matches);
            if (isset($matches[1]) == true) {
                // Extract the theme name
                $theme = $matches[1];
            }

            $matches = [];
            preg_match('/themes\/(.*?)\/(.*?)\.html/', $layoutFile, $matches);
            if (isset($matches[2]) == true) {
                // Extract the layout name
                $layout = $matches[2];
            }
            
            // Organize themes and layouts into an associative array
            $themes[$theme][] = $layout;
        }

        // Set the data to the themes and layouts array        
        $this->data = $themes;
        $this->output();

    }

}