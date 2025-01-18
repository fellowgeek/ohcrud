<?php
namespace app\components;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class SEO extends \app\components\Component {

    public function output($parameters = []) {

        // include meta tags
        if (isset($parameters['keywords']) == true) {
            $this->includeMetaTags('keywords', $parameters['keywords']);
        }

        if (isset($parameters['description']) == true) {
            $this->includeMetaTags('description', $parameters['description']);
        }

        if (isset($parameters['author']) == true) {
            $this->includeMetaTags('author', $parameters['author']);
        }

        // component HTML content should go here
        $this->content->html = '';
    }

}
