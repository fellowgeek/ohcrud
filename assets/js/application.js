/*
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
UTILITY FUNCTION
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
*/

var $ = function($queryString) {

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

    var $OhCRUD_Login_Button = $('#OhCRUD_Login_Button');
    if($OhCRUD_Login_Button != null) {
        $OhCRUD_Login_Button.addEventListener('click', function() {

            var $data = {
                USERNAME: $('#OhCRUD_Login_Username').value,
                PASSWORD: window.btoa($('#OhCRUD_Login_Password').value),
                REDIRECT: $('#OhCRUD_Login_Redirect').value
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
                    $('.alert').classList.add('hidden');
                    if($data.REDIRECT != '') {
                        window.location.href = $data.REDIRECT;
                    } else {
                        window.location.href = '/';
                    }
                },
                async function($error) {
                    const $json = await $error.json();
                    $('.alert').innerHTML = `<p>${$json.errors.join()}</p>`;
                    $('.alert').classList.add('alert-danger');
                    $('.alert').classList.remove('hidden');
                }
            );
        });
    }

});