<?php
namespace app\controllers;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class cThemes extends \OhCrud\Controller {

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'getThemes' => 1,
    ];

    // get themes and layouts
    public function getThemes() {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

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

        $this->data = $themes;
        $this->output();

    }

}