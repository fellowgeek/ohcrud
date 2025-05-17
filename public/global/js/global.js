// Function to log messages to the console when debugMode is enabled
function debugLog(...message) {
    if (__OHCRUD_DEBUG_MODE__ == true) {
        console.log(...message);
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

// Execute the following code when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {

    // Handle the edit button
    let btnCMSEdit = document.getElementById('btnCMSEdit');
    if (btnCMSEdit != null) {
        btnCMSEdit.addEventListener('click', function() {
            window.location.href = btnCMSEdit.dataset.url;
        });
    }

});