<?php
namespace app\components\CMS\ContactCard;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

/*

  ContactCard Component

  This component is used to display a contact card with title, contact information, link, meta info, and group.
  Usage:
  [[CMS\ContactCard\ContactCard|title={Title}|contact={Contact Info}|link={Link URL}|meta={Meta Info}|group={Group}]]

  Example:
  [[CMS\ContactCard\ContactCard|title=Email Support|link=mailto:support@focusapp.com|contact=support@focusapp.com|meta=Report bugs & contribute|group=one]]

*/

class ContactCard extends \app\components\Component {

    // Constructor for this class, which takes a $request parameter.
    public function __construct($request, $path) {
        parent::__construct($request, $path);
        // Set the 'directory' property to the relative path from this script's location to the document root.
        $this->directory = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])) . '/';
    }

    public function output($parameters = []) {

        // Set default values for parameters if they are not provided
        $parameters['title'] = $parameters['title'] ?? '';
        $parameters['contact'] = $parameters['contact'] ?? '';
        $parameters['link'] = $parameters['link'] ?? '';
        $parameters['meta'] = $parameters['meta'] ?? '';
        $parameters['group'] = $parameters['group'] ?? 'first';


        // Set component parameters to be extracted in the view
        $this->variables = $parameters;

        // Include component specific JS assets
        $this->includeJSFile($this->directory . 'js/script.js');

        // Component HTML content
        $this->content->html = $this->loadView($this->directory . 'views/ContactCardView.phtml');

    }

}
