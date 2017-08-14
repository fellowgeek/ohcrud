<?php
namespace App\Controllers;

// prevent direct access
if(isset($GLOBALS['OHCRUD']) == false) { die(); }

class CMS extends \OhCrud\DB {

    public $theme = 'default.php';
    public $defualtView = 'Home';
    public $currentView = '';
    public $title = '';
    public $body = '';

    public function __construct() {
        parent::__construct();
    }

    public function defaultPathHandler($path, $pathArray) {

        $this->outputType = 'HTML';

        ob_start();
        $this->getView($path, $pathArray);
        $this->body = ob_get_clean();

        ob_start();
        include __SELF__ . 'app/views/cms/theme/' . $this->theme;
		$this->data = ob_get_clean();

        $this->output();
    }

    private function getView($path, $pathArray) {

        $text = $path;

        if(empty($text) == true) {
            $this->currentView = $this->defualtView;
        } else {
            $text = str_replace('/' , ' ', $text);
            $text = ucwords($text);
            $text = str_replace(' ' , '/', $text);
            $text = rtrim($text, '/');
            $this->currentView = $text;
        }

        if(file_exists(__SELF__ . 'app/views/cms/' . $this->currentView . '.phtml') == true) {
            include( __SELF__ . 'app/views/cms/' . $this->currentView . '.phtml');
            return true;
        }

        if(file_exists(__SELF__ . 'app/views/cms/' . $this->currentView . '/Index.phtml') == true) {
            include( __SELF__ . 'app/views/cms/' . $this->currentView . '/Index.phtml');
            return true;
        }

        // 404 page not found
        $this->outputStatusCode = 404;
        include(__SELF__ . 'app/views/cms/404.phtml');
        return false;
    }

}
?>