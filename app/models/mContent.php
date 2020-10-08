<?php
namespace app\models;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class mContent {

    const TYPE_DB = 'DB';
    const TYPE_FILE = 'FILE';

    public $theme = __OHCRUD_CMS_DEFAULT_THEME__;
    public $layout = __OHCRUD_CMS_DEFAULT_LAYOUT__;
    public $type = '';
    public $title = '';
    public $text = '';
    public $html = '';
    public $is404 = false;
    public $isDeleted = false;

}
