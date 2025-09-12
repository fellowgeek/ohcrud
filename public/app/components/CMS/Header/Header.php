<?php
namespace app\components\CMS\Header;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

/*

  Header Component

  This component is used to display the article header, designed to work best with the "focus" theme.

  Usage:
  [[CMS\Header\Header|eyebrow=eyebrow|title=title|author=author|updated=updated]]

  Example:
  [[CMS\Header\Header|eyebrow=The Constitutional Foundation of Innovation|title=How a Young Nation Shaped the Modern World|author=Erfan Reed|updated=September 11, 2025]]

*/

class Header extends \app\components\Component {

    // Constructor for this class, which takes a $request parameter.
    public function __construct($request, $path) {
        parent::__construct($request, $path);
        // Set the 'directory' property to the relative path from this script's location to the document root.
        $this->directory = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])) . '/';
    }

    public function output($parameters = []) {

        $parameters['eyebrow'] = $parameters['eyebrow'] ?? '';
        $parameters['title'] = $parameters['title'] ?? '';
        $parameters['author'] = $parameters['author'] ?? '';
        $parameters['updated'] = $parameters['updated'] ?? '';

        // Set component parameters to be extraxted in the view
        $this->variables = $parameters;

        // Component HTML content
        $this->content->html = $this->loadView($this->directory . 'views/HeaderView.phtml');

    }

}
