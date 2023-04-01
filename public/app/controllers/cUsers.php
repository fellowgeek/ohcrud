<?php
namespace app\controllers;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class cUsers extends \OhCrud\Core {

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'login' => __OHCRUD_PERMISSION_ALL__,
        'logout' => __OHCRUD_PERMISSION_ALL__
    ];

    public function login($request) {

        $this->setOutputType('JSON');

        // variables
        $userHasLoggedIn = false;

        // validation
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        if (isset($request->payload) == false || empty($request->payload->USERNAME) == true || empty($request->payload->PASSWORD) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        $Users = new \OhCrud\Users;
        $userHasLoggedIn = $Users->login($request->payload->USERNAME, \base64_decode($request->payload->PASSWORD));

        if ($userHasLoggedIn == false) {
            $this->error('Unable to login, check your Username and Password.');
            $this->output();
            return $this;
        }

        $this->output();

    }

    public function logout($request) {

        $this->setOutputType('JSON');

        $Users = new \OhCrud\Users;
        $userHasLoggedIn = $Users->logout();

        $this->output();

    }

}
