/*
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
UTILITY FUNCTION
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
*/

var $$ = function($queryString) {

    return document.querySelector($queryString);

}

// perform ajax call using fetch API
async function httpRequest($url, $options, $successCallback, $errorCallback) {

    if (typeof $options == 'undefined')
        $options = {};
    try {
        const $fetchResult = fetch($url, $options);
        const $response = await $fetchResult;
        if ($response.ok) {
            if (typeof $successCallback != 'undefined')
                $successCallback($response);
        } else {
            if (typeof $errorCallback != 'undefined')
                $errorCallback($response);
        }
    } catch ($error) {
        if (typeof $errorCallback != 'undefined')
            $errorCallback($error);
    }

}

/*
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
STARTUP
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
*/

document.addEventListener('DOMContentLoaded', function () {

    var $loginButton = $$('#ohcrud-login-login');
    if ($loginButton != null) {
        $loginButton.addEventListener('click', function() {
            var $this = this;
            var $loginForm = $$('#ohcrud-login');
            $this.disabled = true;
            $this.classList.add('btn-disabled');
            $this.innerHTML = `<img class="ohcrud-loader" src="/global-assets/images/loader.svg" />`;

            var $data = Object.fromEntries(new FormData($loginForm).entries());

            httpRequest('/api/users/login/',
                {
                    method: 'POST',
                    cache: 'no-cache',
                    credentials: 'same-origin',
                    body: JSON.stringify($data),
                    headers: new Headers(
                        {
                            'Content-Type': 'application/json'
                        }
                    )
                },
                async function($response) {
                    const $json = await $response.json();

                    // remove alerts (if any)
                    $$('.alert').classList.add('hidden');

                    // handle response based on TOTP status
                    if ($json.data.TOTP === 1) {
                        if ($json.data.loggedIn == true && $json.data.TOTPVerified == false) {
                            // hide the login form and show the TOTP form
                            $$('#ohcrud-login').classList.add('hidden');
                            $$('#ohcrud-totp').classList.remove('hidden');
                        }
                    } else {
                        if ($data.REDIRECT != '') {
                            window.location.href = $data.REDIRECT;
                        } else {
                            window.location.href = '/';
                        }
                    }
                },
                async function($error) {
                    const $json = await $error.json();
                    $$('.alert').innerHTML = `<p>${$json.errors.join()}</p>`;
                    $$('.alert').classList.add('alert-danger');
                    $$('.alert').classList.remove('hidden');
                    $this.disabled = false;
                    $this.classList.remove('btn-disabled');
                    $this.innerHTML = `Login`;
                }
            );
        });
    }

    var $totpButton = $$('#ohcrud-login-totp');
    if ($totpButton != null) {
        $totpButton.addEventListener('click', function() {
            var $this = this;
            var $totpForm = $$('#ohcrud-totp');
            $this.disabled = true;
            $this.classList.add('btn-disabled');
            $this.innerHTML = `<img class="ohcrud-loader" src="/global-assets/images/loader.svg" />`;

            var $data = Object.fromEntries(new FormData($totpForm).entries());

            httpRequest('/api/users/verify/',
                {
                    method: 'POST',
                    cache: 'no-cache',
                    credentials: 'same-origin',
                    body: JSON.stringify($data),
                    headers: new Headers(
                        {
                            'Content-Type': 'application/json'
                        }
                    )
                },
                async function($response) {
                    const $json = await $response.json();
                    if ($data.REDIRECT != '') {
                        window.location.href = $data.REDIRECT;
                    } else {
                        window.location.href = '/';
                    }
                },
                async function($error) {
                    const $json = await $error.json();
                    $$('.alert').innerHTML = `<p>${$json.errors.join()}</p>`;
                    $$('.alert').classList.add('alert-danger');
                    $$('.alert').classList.remove('hidden');
                    $this.disabled = false;
                    $this.classList.remove('btn-disabled');
                    $this.innerHTML = `Veirfy`;
                }
            );

        });
    }

    var $editButton = $$('#ohcrud-editor-edit');
    if ($editButton != null) {
        $editButton.addEventListener('click', function() {
            window.location.href = this.dataset.url;
        });
    }

});