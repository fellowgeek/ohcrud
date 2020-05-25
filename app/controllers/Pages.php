<?php
namespace app\controllers;

use HTMLPurifier;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Pages extends \app\models\Pages {

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'save' => 1
    ];

    public function save($request) {

        $this->setOutputType('JSON');

        // validation
        if (isset($request->payload) == false || empty($request->payload->URL) == true || empty($request->payload->NAME) == true || empty($request->payload->TEXT) == true)
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

        $request->payload->NAME = $purifier->purify($request->payload->NAME);
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
                'NAME' => $request->payload->NAME,
                'TEXT' => $request->payload->TEXT,
                'STATUS' => \app\models\Pages::STATUS_ACTIVE
                ]
            );
        } else {
            // update the record
            $this->update('Pages',
                [
                    'NAME' => $request->payload->NAME,
                    'TEXT' => $request->payload->TEXT,
                    'STATUS' => \app\models\Pages::STATUS_ACTIVE
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
