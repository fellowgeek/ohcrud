<?php
namespace app\components\CMS\FeatureCard;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

/*

  FeatureCard Component

  This component is used to display a feature card with an icon, caption, details, and a link.

  Usage:
  [[CMS\FeatureCard\FeatureCard|icon={Material Icon Name}|link={Link URL}|caption={Caption Text}|details={Detailed Description}|group={Group}]]

  Example:
  [[CMS\FeatureCard\FeatureCard|icon=flash_on|caption=Lightning Fast|details=Optimized for speed and efficiency. Launch instantly and navigate seamlessly without lag or delays. Your time is valuable—we respect that.|group=one]]

*/

class FeatureCard extends \app\components\Component {

    // Constructor for this class, which takes a $request parameter.
    public function __construct($request, $path) {
        parent::__construct($request, $path);
        // Set the 'directory' property to the relative path from this script's location to the document root.
        $this->directory = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])) . '/';
    }

    public function output($parameters = []) {

        // Set default values for parameters if they are not provided
        $parameters['icon'] = $parameters['icon'] ?? '';
        $parameters['link'] = $parameters['link'] ?? '';
        $parameters['caption'] = $parameters['caption'] ?? '';
        $parameters['details'] = $parameters['details'] ?? '';
        $parameters['group'] = $parameters['group'] ?? 'first';


        // Set component parameters to be extracted in the view
        $this->variables = $parameters;

        // Include component specific JS assets
        $this->includeJSFile($this->directory . 'js/script.js');

        // Component HTML content
        $this->content->html = $this->loadView($this->directory . 'views/FeatureCardView.phtml');

    }

}
