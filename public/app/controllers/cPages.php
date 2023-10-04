<?php
namespace app\controllers;

use HTMLPurifier;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class cPages extends \app\models\mPages {

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'save' => 1,
        'restoreDeletePage' => 1
    ];

    public function restoreDeletePage($request) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // validation
        if (isset($request->payload) == false || empty($request->payload->URL) == true)
            $this->error('Missing or incomplete data.');

        // check if page is hard-coded as file
        if (\file_exists(__SELF__ . 'app/views/cms/' . trim($request->payload->URL ?? '', '/') . '.phtml') == true)
            $this->error('You can\'t delete hard coded page.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        // check if page exists
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
            // update the record
            $this->update('Pages',
                [
                    'STATUS' => ($page->STATUS == \app\models\mPages::ACTIVE) ? \app\models\mPages::INACTIVE : \app\models\mPages::ACTIVE
                ],
                'URL = :URL',
                [
                    ':URL' => $request->payload->URL
                ]
            );
        }

        $this->output();

    }

    public function save($request) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // validation
        if (isset($request->payload) == false || empty($request->payload->URL) == true || empty($request->payload->TITLE) == true || empty($request->payload->TEXT) == true)
            $this->error('Missing or incomplete data.');

        // check if page is hard-coded as file
        if (\file_exists(__SELF__ . 'app/views/cms/' . trim($request->payload->URL ?? '', '/') . '.phtml') == true)
            $this->error('You can\'t edit this page.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        // cleanup input
        $purifier = new HTMLPurifier();
        $purifier->config->set('HTML.SafeIframe', true);
        $purifier->config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www\.youtube(?:-nocookie)?\.com/embed/|player\.vimeo\.com/video/)%');

        $request->payload->TITLE = $purifier->purify($request->payload->TITLE);
        $request->payload->TEXT = $purifier->purify($request->payload->TEXT);

        // check if page exists
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
            // create the record
            $this->create('Pages', [
                'URL' => $request->payload->URL,
                'TITLE' => $request->payload->TITLE,
                'TEXT' => $request->payload->TEXT,
                'THEME' => $request->payload->THEME,
                'LAYOUT' => $request->payload->LAYOUT,
                'STATUS' => \app\models\mPages::ACTIVE
                ]
            );
        } else {
            // update the record
            $this->update('Pages',
                [
                    'TITLE' => $request->payload->TITLE,
                    'TEXT' => $request->payload->TEXT,
                    'THEME' => $request->payload->THEME,
                    'LAYOUT' => $request->payload->LAYOUT,
                    'STATUS' => \app\models\mPages::ACTIVE
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
