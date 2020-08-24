<?php
namespace app\models;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Content {

    const TYPE_DB = 'DB';
    const TYPE_FILE = 'FILE';

    public $theme = '';
    public $layout = '';
    public $type = '';
    public $title = '';
    public $text = '';
    public $html = '';
    public $is404 = false;

}
