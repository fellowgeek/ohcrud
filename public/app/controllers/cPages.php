<?php
namespace app\controllers;

use HTMLPurifier;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cPages - pages controller used by the CMS
class cPages extends \app\models\mPages {

    // Method to save or edit a page.
    #[\ohCRUD\Endpoint(permission: 1, methods: ['POST'])]
    public function save($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload contains the necessary data.
        if (isset($request->payload) == false ||
            empty($request->payload->URL) == true ||
            empty($request->payload->TITLE) == true ||
            empty($request->payload->TEXT) == true ||
            isset($request->payload->STATUS) == false)
            $this->error('Missing or incomplete data.');

        // Check if the page is hard-coded as a file.
        if ($this->isHardCoded($request->payload->URL) == true)
            $this->error('You can\'t edit a hard coded page.', 403);

        if ($this->success === false) {
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

        // Theme selections are names, never filesystem paths or HTML attributes.
        foreach (['THEME', 'LAYOUT'] as $field) {
            if (is_string($request->payload->$field) == false || preg_match('/^[a-zA-Z0-9_-]+$/D', $request->payload->$field) !== 1) {
                return $this->error('Invalid theme or layout.')->output();
            }
        }

        // Set default value for STATUS if missing or invalid in the payload.
        if (in_array((int) $request->payload->STATUS, [$this::PUBLISHED, $this::DELETED, $this::DRAFT]) == true) {
            $request->payload->STATUS = (int) $request->payload->STATUS;
        } else {
            $request->payload->STATUS = $this::DRAFT;
        }

        // Check if the page exists in the database.
        $PageExists = $this->run(
            "SELECT
                `ID`
            FROM
                `Pages`
            WHERE
                `URL` = :URL
            ",
            [
                ':URL' => $request->payload->URL
            ]
        )->first();

        if ($PageExists === false) {
            // Create a new record in the 'Pages' table.
            $this->create('Pages', [
                'URL' => $request->payload->URL,
                'TITLE' => $request->payload->TITLE,
                'TEXT' => $request->payload->TEXT,
                'THEME' => $request->payload->THEME,
                'LAYOUT' => $request->payload->LAYOUT,
                'STATUS' => $request->payload->STATUS,
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
                    'STATUS' => $request->payload->STATUS
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
    #[\ohCRUD\Endpoint(permission: 1, methods: ['POST'])]
    public function restoreDeletePage($request) {

        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check if the request payload is complete.
        if (isset($request->payload) == false || empty($request->payload->URL) == true)
            $this->error('Missing or incomplete data.');

        // Check if the page is hard-coded as a file.
        if ($this->isHardCoded($request->payload->URL) == true)
            $this->error('You can\'t delete a hard coded page.', 403);

        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Check if the page exists in the database.
        $page = $this->run(
            "SELECT
                *
            FROM
                `Pages`
            WHERE
                `URL` = :URL
            ",
            [
                ':URL' => $request->payload->URL
            ]
        )->first();

        if ($page !== false) {
            // Update the record: toggle the STATUS between PUBLISHED and DELETED.
            $this->update('Pages',
                [
                    'STATUS' => ((int) $page->STATUS == $this::PUBLISHED) ? $this::DELETED : $this::PUBLISHED
                ],
                'URL = :URL',
                [
                    ':URL' => $request->payload->URL
                ]
            );
        }

        $this->output();
    }

    private function isHardCoded($url) {
        $path = trim($url ?? '', '/');
        if (empty($path)) {
            return false;
        }
        $fullPath = __SELF__ . 'app/views/cms/' . $path . '.phtml';

        $baseDir = realpath(__SELF__ . 'app/views/cms');
        $realFullPath = realpath($fullPath);

        if ($realFullPath !== false && $baseDir !== false && strpos($realFullPath, $baseDir . DIRECTORY_SEPARATOR) === 0 && file_exists($realFullPath)) {
            return true;
        }

        return false;
    }

}
