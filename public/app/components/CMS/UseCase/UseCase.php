<?php
namespace app\components\CMS\UseCase;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

/*

  UseCase Component

  This component is used to display

  Usage:
  [[CMS\UseCase\UseCase|caption={Caption Text}|details={Detailed Description}]]

  Example:
  [[CMS\UseCase\UseCase|caption=For Creative Professionals|details=Manage multiple research projects, organize notes, and maintain deep focus during study sessions.]]

*/

class UseCase extends \app\components\Component {

    // Constructor for this class, which takes a $request parameter.
    public function __construct($request, $path) {
        parent::__construct($request, $path);
        // Set the 'directory' property to the relative path from this script's location to the document root.
        $this->directory = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])) . '/';
    }

    public function output($parameters = []) {

        // Set default values for parameters if they are not provided
        $parameters['caption'] = $parameters['caption'] ?? '';
        $parameters['details'] = $parameters['details'] ?? '';

        // Set component parameters to be extracted in the view
        $this->variables = $parameters;

        // Component HTML content
        $this->content->html = $this->loadView($this->directory . 'views/UseCaseView.phtml');

    }

}
