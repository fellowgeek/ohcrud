<?php
namespace app\components\CMS\PostCard;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

/*

  PostCard Component

  This component is used to display a post card with captions and link to a post, designed to work best with the "focus" theme.

  Usage:
  [[CMS\PostCard\PostCard|image={Image URL}|alt={Alt Text}|link={Link URL}|caption={Caption Text}|meta={Meta Info}|group={Group}]]

  Example:
  [[CMS\PostCard\PostCard|image=/global/files/0ac58acf2942094b720b5ad9794efa33.jpg|alt=Drowning in pop-ups: the modern worker’s daily battle with a thousand Clippies.|link=/posts/welcome-to-the-age-of-a-thousand-clippies|caption=Welcome to the Age of a Thousand Clippies|meta=Published: Sep 29, 2025|group=one]]

*/

class PostCard extends \app\components\Component {

    // Constructor for this class, which takes a $request parameter.
    public function __construct($request, $path) {
        parent::__construct($request, $path);
        // Set the 'directory' property to the relative path from this script's location to the document root.
        $this->directory = substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])) . '/';
    }

    public function output($parameters = []) {


        $parameters['image'] = $parameters['image'] ?? '';
        $parameters['alt'] = $parameters['alt'] ?? '';
        $parameters['link'] = $parameters['link'] ?? '';
        $parameters['caption'] = $parameters['caption'] ?? '';
        $parameters['meta'] = $parameters['meta'] ?? '';
        $parameters['group'] = $parameters['group'] ?? 'first';


        // Set component parameters to be extracted in the view
        $this->variables = $parameters;

        // Include component specific JS assets
        $this->includeJSFile($this->directory . 'js/script.js');

        // Component HTML content
        $this->content->html = $this->loadView($this->directory . 'views/PostCardView.phtml');

    }

}
