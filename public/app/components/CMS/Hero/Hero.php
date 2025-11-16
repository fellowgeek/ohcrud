<?php
namespace app\components\CMS\Hero;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

/*

  Hero Component

  This component is used to display a

  Usage:
  [[CMS\Hero\Hero|title={Title Text}|details={Detailed Description}|image={Image URL}|alt={Alt Text}|cta={Call to Action Text}|link={Link URL}]]

  Example:
  [[CMS\Hero\Hero|title=Focus on What Matters Most|details=A beautifully simple app designed to help you stay focused, organized, and productive. Experience distraction-free work with elegant design and powerful features.|image=/global/files/f8b618b956d4b4008783a4604f9348cf.jpg|alt=Mockup Image|cta=Download Now|link=https://www.example.com]]

*/

class Hero extends \app\components\Component {

    // Constructor for this class, which takes a $request parameter.
    public function __construct($request, $path) {
        parent::__construct($request, $path);
        // Set the 'directory' property to the relative path from this script's location to the document root.
        $this->directory = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])) . '/';
    }

    public function output($parameters = []) {

        // Set default values for parameters if they are not provided
        $parameters['title'] = $parameters['title'] ?? '';
        $parameters['details'] = $parameters['details'] ?? '';
        $parameters['image'] = $parameters['image'] ?? '';
        $parameters['alt'] = $parameters['alt'] ?? '';
        $parameters['cta'] = $parameters['cta'] ?? '';
        $parameters['link'] = $parameters['link'] ?? '';

        // Set component parameters to be extraxted in the view
        $this->variables = $parameters;

        // Component HTML content
        $this->content->html = $this->loadView($this->directory . 'views/HeroView.phtml');

    }

}
