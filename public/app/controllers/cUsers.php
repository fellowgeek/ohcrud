<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cUsers - users controller used by the OhCRUD framework
class cUsers extends \ohCRUD\DB {

    // Define permissions for the controller.
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'login' => __OHCRUD_PERMISSION_ALL__,
        'verify' => __OHCRUD_PERMISSION_ALL__,
        'logout' => __OHCRUD_PERMISSION_ALL__,
    ];

    // This method, handles user login functionality
    public function login($request) {

        // Sets the output type for this controller to JSON.
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables and creates an instance of the 'ohCRUD\Users' class.
        $this->data = new \stdClass();
        $Users = new \ohCRUD\Users;

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check for missing or incomplete user login data
        if (isset($request->payload) == false || empty($request->payload->USERNAME) == true || empty($request->payload->PASSWORD) == true)
            $this->error('Missing or incomplete data.');

        // If there are errors, output them and return
        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Attempt user login
        $response = $Users->login($request->payload->USERNAME, $request->payload->PASSWORD);

        // If login is unsuccessful, output an error and return
        if ($response === false) {
            $this->error('Unable to login, check your Username and Password.', 401);
            $this->output();
            return $this;
        }

        // Regenerate session ID
        $this->regenerateSession();

        // Populate data object with response data
        $this->data->TOTP = $response->TOTP;
        $this->data->TOTPVerified = $response->TOTPVerified ?? false;
        $this->data->loggedIn = $response->loggedIn ?? false;
        $this->data->REDIRECT = $request->payload->REDIRECT ?? '';

        $this->output();
        return $this;
    }

    // Handles user TOTP verification after login
    public function verify($request) {

        // Sets the output type for this controller to JSON.
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Initializes variables and creates an instance of the 'ohCRUD\Users' class.
        $this->data = new \stdClass();
        $Users = new \ohCRUD\Users;

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false)
            $this->error('Missing or invalid CSRF token.');

        // Check for missing or incomplete verification data
        if (isset($request->payload) == false || empty($request->payload->TOTP) == true)
            $this->error('Missing or incomplete data.');

        // Check if the user has logged in yet (in the current temporary session)
        if (isset($_SESSION['tempUser']) == false)
            $this->error('User has not logged in yet.', 401);

        // If there are errors, output them and return
        if ($this->success === false) {
            $this->output();
            return $this;
        }

        // Attempt two-factor authentication verification
        $response = $Users->verify($_SESSION['tempUser']->ID, $request->payload->TOTP);

        // If verification is unsuccessful, output an error and return
        if ($response === false) {
            $this->error('Unable to verify, check your two factor authentication code.', 401);
            $this->output();
            return $this;
        }

        // Regenerate session ID
        $this->regenerateSession();

        // Set user as logged in and set the REDIRECT property in the data object
        $this->data->loggedIn = true;
        $this->data->REDIRECT = $request->payload->REDIRECT ?? '';
        $this->output();
        return $this;
    }

    // Handles user logout
    public function logout($request) {

        // Sets the output type for this controller to JSON.
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);

        // Unset the User and tempUser sessions (user logout)
        $this->unsetSession('User');
        $this->unsetSession('tempUser');

        // Regenerate session ID
        $this->regenerateSession();

        $this->output();
    }

}
