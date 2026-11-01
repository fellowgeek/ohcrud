// Function to log messages to the console when debugMode is enabled
function debugLog(...message) {
    if (__OHCRUD_DEBUG_MODE__ == true) {
        console.log(...message);
    }
}

// Define a function for making an AJAX call using the fetch API
async function httpRequest(url, options, successCallback, errorCallback, isRaw = false) {
    // Auto add CSRF token and stringify the body if needed
    if (options.body && !isRaw) {
        options.body.CSRF = __CSRF__;
        options.body = JSON.stringify(options.body);
    }

    let failure;
    try {
        const response = await fetch(url, options);
        if (options.signal && options.signal.aborted === true) return;
        if (response.ok === true) {
            if (typeof successCallback === 'function') {
                await successCallback(response);
            }
            return;
        }
        failure = response;
    } catch (error) {
        if (options.signal && options.signal.aborted === true) return;
        failure = error;
    }

    // Existing callbacks can read JSON for HTTP, network and malformed-response errors.
    if (typeof errorCallback === 'function') {
        const fallback = {success: false, errors: ['The request failed. Please try again.']};
        const errorResponse = {
            ok: false,
            status: failure.status || 0,
            json: async function () {
                if (typeof failure.json === 'function') {
                    try {
                        return await failure.json();
                    } catch (error) {
                        return fallback;
                    }
                }
                return fallback;
            }
        };
        await errorCallback(errorResponse);
    }
}

// Execute the following code when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {

    // Handle the edit button
    let btnCMSEdit = document.getElementById('btnCMSEdit');
    if (btnCMSEdit != null) {
        btnCMSEdit.addEventListener('click', function () {
            window.location.href = btnCMSEdit.dataset.url;
        });
    }
});
