
// Define a shorthand function to select a DOM element using document.querySelector
let select = function(queryString) {
    return document.querySelector(queryString);
}

// Function to log messages to the console when debugMode is enabled
function debugLog(...message) {
    if (__OHCRUD_DEBUG_MODE__ == true) {
        console.log(...message);
    }
}

// Define a function to recreate a DOM element (i.e. for removing event listeners)
function recreateNode(el, withChildren) {
    if (withChildren) {
        el.parentNode.replaceChild(el.cloneNode(true), el);
    } else {
        var newEl = el.cloneNode(false);
        while (el.hasChildNodes()) newEl.appendChild(el.firstChild);
        el.parentNode.replaceChild(newEl, el);
    }
}

// Define a function for making an AJAX call using the fetch API
async function httpRequest(url, options, successCallback, errorCallback, isRaw = false) {
    if (typeof options == 'undefined')
        options = {};

    // Auto add CSRF token and stringify the body
    if (typeof options.body != 'undefined' && isRaw == false) {
        options.body.CSRF = __CSRF__;
        options.body = JSON.stringify(options.body);
    }
    try {
        const fetchResult = fetch(url, options);
        const response = await fetchResult;
        if (response.ok) {
            if (typeof successCallback != 'undefined')
                successCallback(response);
        } else {
            if (typeof errorCallback != 'undefined')
                errorCallback(response);
        }
    } catch (error) {
        if (typeof errorCallback != 'undefined')
            errorCallback(error);
    }
}

// Start: Execute the following code when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {

    // Handle login button
    let loginButton = select('#ohcrud-login-login');
    if (loginButton != null) {
        loginButton.addEventListener('click', function() {
            let loginForm = select('#ohcrud-login');
            loginButton.disabled = true;
            loginButton.classList.add('btn-disabled');
            loginButton.innerHTML = `<img class="ohcrud-loader" src="/global-assets/images/loader.svg" />`;
            let data = Object.fromEntries(new FormData(loginForm).entries());

            httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/users/login/',
                {
                    method: 'POST',
                    cache: 'no-cache',
                    credentials: 'same-origin',
                    body: data,
                    headers: new Headers(
                        {
                            'Content-Type': 'application/json'
                        }
                    )
                },
                async function(response) {
                    const json = await response.json();
                    // Remove any alerts (if any)
                    select('.alert').classList.add('hidden');
                    // Handle the response based on TOTP status
                    if (json.data.TOTP === 1) {
                        if (json.data.loggedIn == true && json.data.TOTPVerified == false) {
                            // Hide the login form and show the TOTP form
                            select('#ohcrud-login').classList.add('hidden');
                            select('#ohcrud-totp').classList.remove('hidden');
                        }
                    } else {
                        if (data.REDIRECT != '') {
                            window.location.href = data.REDIRECT;
                        } else {
                            window.location.href = '/';
                        }
                    }
                },
                async function(error) {
                    const json = await error.json();
                    // Display error messages in an alert element
                    select('.alert').innerHTML = `<p>${json.errors.join()}</p>`;
                    select('.alert').classList.add('alert-danger');
                    select('.alert').classList.remove('hidden');
                    // Re-enable the button and change its content back to "Login"
                    loginButton.disabled = false;
                    loginButton.classList.remove('btn-disabled');
                    loginButton.innerHTML = `Login`;
                }
            );
        });
    }

    // Handle TOTP (Time-based One-Time Password) verification button
    let totpButton = select('#ohcrud-login-totp');
    if (totpButton != null) {
        totpButton.addEventListener('click', function() {
            let totpForm = select('#ohcrud-totp');
            totpButton.disabled = true;
            totpButton.classList.add('btn-disabled');
            totpButton.innerHTML = `<img class="ohcrud-loader" src="/global-assets/images/loader.svg" />`;
            let data = Object.fromEntries(new FormData(totpForm).entries());

            httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/users/verify/',
                {
                    method: 'POST',
                    cache: 'no-cache',
                    credentials: 'same-origin',
                    body: data,
                    headers: new Headers(
                        {
                            'Content-Type': 'application/json'
                        }
                    )
                },
                async function(response) {
                    const json = await response.json();
                    if (data.REDIRECT != '') {
                        window.location.href = data.REDIRECT;
                    } else {
                        window.location.href = '/';
                    }
                },
                async function(error) {
                    const json = await error.json();
                    select('.alert').innerHTML = `<p>${json.errors.join()}</p>`;
                    select('.alert').classList.add('alert-danger');
                    select('.alert').classList.remove('hidden');
                    totpButton.disabled = false;
                    totpButton.classList.remove('btn-disabled');
                    totpButton.innerHTML = `Veirfy`;
                }
            );

        });
    }

    // Handle the edit button
    let editButton = select('#ohcrud-editor-edit');
    if (editButton != null) {
        editButton.addEventListener('click', function() {
            window.location.href = editButton.dataset.url;
        });
    }

});