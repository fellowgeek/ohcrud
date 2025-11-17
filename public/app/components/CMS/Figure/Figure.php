<?php
namespace app\components\CMS\Figure;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

/*

  Figure Component

  This component is used to display a figure (image) with captions, designed to work best with the "focus" theme.

  Usage:
  [[CMS\Figure\Figure|image={Image URL}|alt={Alt Text}|caption={Caption Text}]]

  Example:
  [[CMS\Figure\Figure|image=/global/files/76617425834173c813283a6ec0e89d49.jpg|alt=Astronaut Buzz Aldrin|caption=Astronaut Buzz Aldrin - NASA]]

*/

class Figure extends \app\components\Component {

    // Constructor for this class, which takes a $request parameter.
    public function __construct($request, $path) {
        parent::__construct($request, $path);
        // Set the 'directory' property to the relative path from this script's location to the document root.
        $this->directory = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])) . '/';
    }

    public function output($parameters = []) {


        $parameters['image'] = $parameters['image'] ?? '';
        $parameters['alt'] = $parameters['alt'] ?? '';
        $parameters['caption'] = $parameters['caption'] ?? '';

        // Set component parameters to be extraxted in the view
        $this->variables = $parameters;

        // Component HTML content
        $this->content->html = $this->loadView($this->directory . 'views/FigureView.phtml');

    }

}
