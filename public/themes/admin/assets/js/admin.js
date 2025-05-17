// Framework7 app instance
var app = null;
// Assign Dom7 to $$ for easier DOM manipulation
var $$ = Dom7;

// Execute the following code when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {
    // Initialize Framework7 app with iOS theme
    var theme = 'ios';
    app = new Framework7({
        el: '#app', // Root element
        theme, // App theme
        name: 'ohCRUD', // App name
        panel: {
            swipe: false,
            resizable: false,
        }, // Enable swipe panel
    });
});

// Event listener for page initialization
$$(document).on('page:init', function (e, page) {
    // Log the page initialization event
    debugLog('event: "page:init" triggered for "' + page.name + '"');

    // If the login page is initialized
    if(page.name == 'login') {
        // Bring inputs to focus
        $$('#USERNAME').focus();

        // UI buttons
        let btnLogin = $$('#btnLogin');
        let btnLoginCancel = $$('#btnLoginCancel');
        let btnLoginVerify = $$('#btnLoginVerify');

        // Handle login button
        btnLogin.on('click', function() {
            btnLogin.addClass('disabled');
            btnLogin.html(`<img class="ohcrud-loader" src="/global/images/loader.svg" />`);
            let data = {
                USERNAME: $$('#USERNAME').val(),
                PASSWORD: $$('#PASSWORD').val(),
                REDIRECT: $$('#REDIRECT_FULL').val(),
            }

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
                    // Handle the response based on TOTP status
                    if (json.data.TOTP === 1) {
                        if (json.data.loggedIn == true && json.data.TOTPVerified == false) {
                            // Hide the login form and show the TOTP form
                            $$('.card-login').addClass('hidden');
                            $$('.card-totp').removeClass('hidden');
                            $$('#TOTP').focus();
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
                    notify({
                        icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                        title: 'ohCRUD!',
                        titleRightText: 'now',
                        text: json.errors.join(),
                        closeOnClick: true,
                    });
                    // Re-enable the button and change its content back to "Login"
                    btnLogin.removeClass('disabled');
                    btnLogin.html('LOGIN');
                }
            );
        });

        // Handle the cancel login button
        btnLoginCancel.on('click', function() {
            let REDIRECT_PATH = $$('#REDIRECT_PATH').val();
            if (REDIRECT_PATH != '') {
                window.location.href = REDIRECT_PATH;
            } else {
                window.location.href = '/';
            }
        });

        // Handle TOTP (Time-based One-Time Password) verification button
        btnLoginVerify.on('click', function() {

            btnLoginVerify.addClass('disabled');
            btnLoginVerify.html(`<img class="ohcrud-loader" src="/global/images/loader.svg" />`);
            let data = {
                TOTP: $$('#TOTP').val(),
                REDIRECT: $$('#REDIRECT_FULL').val(),
            }

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
                    // Display error messages in an alert element
                    notify({
                        icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                        title: 'ohCRUD!',
                        titleRightText: 'now',
                        text: json.errors.join(),
                        closeOnClick: true,
                    });
                    btnLoginVerify.removeClass('disabled');
                    btnLoginVerify.html('VERIFY');
                }
            );
        });

    }


});

// This method creates and issues a Framework7 notification
function notify(options = {}) {
    let notificationCloseOnClick = app.notification.create(options);
    notificationCloseOnClick.open();
}

// This function masks input number.
function maskInputNumber(input) {
    let value = input.value;
    // Allow only valid numeric characters
    value = value.replace(/[^0-9]/g, '');
    // Update the input value
    input.value = value;
}