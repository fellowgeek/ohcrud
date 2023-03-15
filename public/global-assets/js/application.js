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

    var $loginForm = $$('#ohcrud-login');
    if ($loginForm != null) {
        $loginForm.addEventListener('submit', function($event) {
            $event.preventDefault();
        });
    }

    var $loginButton = $$('#ohcrud-login-login');
    if ($loginButton != null) {
        $loginButton.addEventListener('click', function() {
            var $this = this;
            $this.disabled = true;
            $this.classList.add('btn-disabled');
            $this.innerHTML = `<img class="ohcrud-loader" src="/global-assets/images/loader.svg" />`;

            var $data = {
                USERNAME: $$('#ohcrud-login-username').value,
                PASSWORD: window.btoa($$('#ohcrud-login-password').value),
                REDIRECT: $$('#ohcrud-login-redirect').value
            };

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
                    $$('.alert').classList.add('hidden');
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
                    $this.innerHTML = `Login`;
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