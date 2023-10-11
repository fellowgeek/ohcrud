<?php
namespace app\controllers;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class cUsers extends \OhCrud\DB {

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'login' => __OHCRUD_PERMISSION_ALL__,
        'verify' => __OHCRUD_PERMISSION_ALL__,
        'logout' => __OHCRUD_PERMISSION_ALL__
    ];

    public function login($request) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // variables
        $this->data = new \stdClass();
        $Users = new \OhCrud\Users;

        // validation
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        if (isset($request->payload) == false || empty($request->payload->USERNAME) == true || empty($request->payload->PASSWORD) == true)
            $this->error('Missing or incomplete data.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        $response = $Users->login($request->payload->USERNAME, $request->payload->PASSWORD);

        if ($response == false) {
            $this->error('Unable to login, check your Username and Password.');
            $this->output();
            return $this;
        }

        $this->data->TOTP = $response->TOTP;
        $this->data->TOTPVerified = $response->TOTPVerified ?? false;
        $this->data->loggedIn = $response->loggedIn ?? false;
        $this->data->REDIRECT = $request->payload->REDIRECT ?? '';

        $this->output();
        return $this;
    }

    public function verify($request) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        // variables
        $this->data = new \stdClass();
        $Users = new \OhCrud\Users;

        // validation
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false)
            $this->error('Missing or invalid CSRF token.');

        if (isset($request->payload) == false || empty($request->payload->TOTP_CODE) == true)
            $this->error('Missing or incomplete data.');

        if (isset($_SESSION['tempUser']) == false)
            $this->error('User has not logged in yet.');

        if ($this->success == false) {
            $this->output();
            return $this;
        }

        $response = $Users->verify($_SESSION['tempUser']->ID, $request->payload->TOTP_CODE);

        if ($response == false) {
            $this->error('Unable to verify, check your two factor authentication code.');
            $this->output();
            return $this;
        }

        $this->data->loggedIn = true;
        $this->data->REDIRECT = $request->payload->REDIRECT ?? '';
        $this->output();
        return $this;
    }

    public function logout($request) {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);

        $this->unsetSession('User');
        $this->unsetSession('tempUser');
        $this->output();
    }

}
