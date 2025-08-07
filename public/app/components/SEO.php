<?php
namespace app\components;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

/*

  SEO Component

  This component is responsible for managing SEO-related meta tags and properties.
  It allows for the inclusion of various meta tags such as canonical, keywords, description,
  author, viewport, robots, Open Graph tags, and Twitter card tags.

  Usage:
  [[SEO|parameter=value, ...]]
  Where `parameter` can be any of the allowed meta tags defined in `$allowedMetaTags`.
  Example: [[SEO|canonical=https://example.com, description=This is an example page, og:title=Example Page]]

  The component will automatically include the specified meta tags in the HTML output.
  It can be used in pages, templates, or components to enhance SEO for the application.

*/

class SEO extends \app\components\Component {

    public $allowedMetaTags = [
        'canonical',
        'keywords',
        'description',
        'author',
        'viewport',
        'robots',
        'og:site_name',
        'og:title',
        'og:description',
        'og:locale',
        'og:type',
        'og:url',
        'og:image',
        'twitter:card',
        'twitter:title',
        'twitter:description',
        'twitter:image',
        'twitter:site',
        'twitter:creator'
    ];

    public function output($parameters = []) {

        // Process the parameters and include allowed meta tags
        foreach ($parameters as $key => $value) {
            if (in_array($key, $this->allowedMetaTags) == true) {
                $this->includeMetaTags($key, $value);
            }
        }

        // component HTML content should go here
        $this->content->html = '';
    }

}
