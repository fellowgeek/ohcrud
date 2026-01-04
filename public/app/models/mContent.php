<?php
namespace app\models;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Model mContent - content model used by the CMS
class mContent {

    // Define constants for content types.
    const TYPE_DB = 'DB';
    const TYPE_FILE = 'FILE';

    // The theme associated with the content.
    public $theme = __OHCRUD_CMS_DEFAULT_THEME__;

    //The layout associated with the content.
    public $layout = __OHCRUD_CMS_DEFAULT_LAYOUT__;

    // The status of the content.
    public $status = \app\models\mPages::PUBLISHED;

    // Indicates the HTTP status code that the content represents.
    public $statusCode = 200;

    // The type of content, either 'DB' or 'FILE'.
    public $type = '';

    // The title of the content.
    public $title = '';

    // The text content.
    public $text = '';

    // The HTML content.
    public $html = '';

    // The meta content.
    public $metaTags = '';

    // The CSS content.
    public $stylesheet = '';

    // The JavaScript content.
    public $javascript = '';

    // Indicates if the content has been marked as deleted.
    public $isDeleted = false;

}
