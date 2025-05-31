<?php
namespace app\controllers;

use HTMLPurifier;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cPages - pages controller used by the CMS
class cPages extends \app\models\mPages {

    // Define permissions for the controller.
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'save' => 1,
        'restoreDeletePage' => 1
    ];

    // Method to save or edit a page.
    public function save($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false || empty($request->payload->URL) == true || empty($request->payload->TITLE) == true || empty($request->payload->TEXT) == true)
            $this->error('Missing or incomplete data.');

        // Check if the page is hard-coded as a file.
        if (\file_exists(__SELF__ . 'app/views/cms/' . trim($request->payload->URL ?? '', '/') . '.phtml') == true)
            $this->error('You can\'t edit this page.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        // Clean up the input by using HTMLPurifier to prevent XSS attacks.
        $purifier = new HTMLPurifier();
        $purifier->config->set('HTML.SafeIframe', true);
        $purifier->config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

        $request->payload->TITLE = $purifier->purify($request->payload->TITLE);
        $request->payload->TEXT = $purifier->purify($request->payload->TEXT);

        // Set default values for THEME and LAYOUT if missing in the payload.
        if (isset($request->payload->THEME) == false) {
            $request->payload->THEME = __OHCRUD_CMS_DEFAULT_THEME__;
        }
        if (isset($request->payload->LAYOUT) == false) {
            $request->payload->LAYOUT = __OHCRUD_CMS_DEFAULT_LAYOUT__;
        }

        // Check if the page exists in the database.
        $PageExists = $this->run(
            "SELECT
                COUNT(*) AS `PageExists`
            FROM
                Pages
            WHERE
                URL = :URL
            ",
            [
                ':URL' => $request->payload->URL
            ]
        )->first()->PageExists;

        if ($PageExists == false) {
            // Create a new record in the 'Pages' table.
            $this->create('Pages', [
                'URL' => $request->payload->URL,
                'TITLE' => $request->payload->TITLE,
                'TEXT' => $request->payload->TEXT,
                'THEME' => $request->payload->THEME,
                'LAYOUT' => $request->payload->LAYOUT,
                'STATUS' => $this::ACTIVE
                ]
            );
        } else {
            // Update the existing record in the 'Pages' table.
            $this->update('Pages',
                [
                    'TITLE' => $request->payload->TITLE,
                    'TEXT' => $request->payload->TEXT,
                    'THEME' => $request->payload->THEME,
                    'LAYOUT' => $request->payload->LAYOUT,
                    'STATUS' => $this::ACTIVE
                ],
                'URL = :URL',
                [
                    ':URL' => $request->payload->URL
                ]
            );
        }

        $this->output();
    }

    // Method to restore or delete a page.
    public function restoreDeletePage($request) {

        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload is complete.
        if (isset($request->payload) == false || empty($request->payload->URL) == true)
            $this->error('Missing or incomplete data.');

        // Check if the page is hard-coded as a file.
        if (\file_exists(__SELF__ . 'app/views/cms/' . trim($request->payload->URL ?? '', '/') . '.phtml') == true)
            $this->error('You can\'t delete hard coded page.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        // Check if the page exists in the database.
        $page = $this->run(
            "SELECT
                *
            FROM
                Pages
            WHERE
                URL = :URL
            ",
            [
                ':URL' => $request->payload->URL
            ]
        )->first();

        if ($page != false) {
            // Update the record: toggle the STATUS between ACTIVE and INACTIVE.
            $this->update('Pages',
                [
                    'STATUS' => ($page->STATUS == $this::ACTIVE) ? $this::INACTIVE : $this::ACTIVE
                ],
                'URL = :URL',
                [
                    ':URL' => $request->payload->URL
                ]
            );
        }

        $this->output();
    }

}
