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

    if(typeof $options == 'undefined')
        $options = {};
    try {
        const $fetchResult = fetch($url, $options);
        const $response = await $fetchResult;
        if ($response.ok) {
            if(typeof $successCallback != 'undefined')
                $successCallback($response);
        } else {
            if(typeof $errorCallback != 'undefined')
                $errorCallback($response);
        }
    } catch ($error) {
        if(typeof $errorCallback != 'undefined')
            $errorCallback($error);
    }

}

/*
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
STARTUP
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
*/

document.addEventListener('DOMContentLoaded', function () {

    console.log('Ready.');

    var $OhCRUD_Login_Button = $$('#ohcrud-login-login');
    if($OhCRUD_Login_Button != null) {
        $OhCRUD_Login_Button.addEventListener('click', function() {

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
                    if($data.REDIRECT != '') {
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
                }
            );
        });
    }

    var $OhCRUD_Edit_Button = $$('#ohcrud-editor-edit');
    if($OhCRUD_Edit_Button != null) {
        $OhCRUD_Edit_Button.addEventListener('click', function() {
            console.log('here');
            window.location.href = this.dataset.url;
        });
    }

});