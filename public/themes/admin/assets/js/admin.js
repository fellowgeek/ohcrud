// Framework7 app instance
var app = null;
// Assign Dom7 to $$ for easier DOM manipulation
var $$ = Dom7;
// global variables
let themes = {};
let ohCrudEditor = null;
let columnDetails = {};
const __CURRENT_THEME__ = $$('#currentTHEME').val();
const __CURRENT_LAYOUT__ = $$('#currentLAYOUT').val();
let file = document.getElementById('FILE');

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
$$(document).on('page:init', function (e, pageObject) {
    // Log the page initialization event
    debugLog('event: "page:init" triggered for "' + pageObject.name + '"');

    // Get global action for the page
    let action = getFromURL('action');

    // Global setup
    let appDarkMode = localStorage.getItem('app:dark-mode');
    if (appDarkMode != null) {
        $$('#appDarkMode').prop('checked', appDarkMode === 'Y' ? true : false);
        setDarkMode(appDarkMode);
    }

    // Global events
    $$('#btnLogout').on('click', function() {
        logout(true);
    });

    $$('#appDarkMode').on('change', function() {
        appDarkMode = $$(this).prop('checked');
        setDarkMode(appDarkMode === true ? 'Y' : 'N');
    });

    // If the login page is initialized
    if (pageObject.name === 'login') {

        // Logout user
        logout();

        // Bring inputs to focus
        $$('#USERNAME').focus();

        // UI buttons
        let btnLogin = $$('#btnLogin');
        let btnLoginCancel = $$('#btnLoginCancel');
        let btnLoginVerify = $$('#btnLoginVerify');

        // UI inputs
        let PASSWORD = $$('#PASSWORD');
        let TOTP = $$('#TOTP');

        // Handle "Enter" key events
        PASSWORD.on('keypress', function (e) {
            if (e.key === 'Enter') {
                btnLogin.trigger('click');
            }
        });

        TOTP.on('keypress', function (e) {
            if (e.key === 'Enter') {
                btnLoginVerify.trigger('click');
            }
        });

        // Handle login button
        btnLogin.on('click', function () {
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
                async function (response) {
                    const json = await response.json();
                    // Handle the response based on TOTP status
                    if (json.data.TOTP === 1) {
                        if (json.data.loggedIn === true && json.data.TOTPVerified === false) {
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
                async function (error) {
                    const json = await error.json();
                    // Display error messages in notification
                    notify({
                        icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                        title: 'ohCRUD!',
                        titleRightText: 'now',
                        text: json.errors.join('<br/>'),
                        closeOnClick: true,
                    });
                    // Re-enable the button and change its content back to "Login"
                    btnLogin.removeClass('disabled');
                    btnLogin.html('LOGIN');
                }
            );
        });

        // Handle the cancel login button
        btnLoginCancel.on('click', function () {
            let REDIRECT_PATH = $$('#REDIRECT_PATH').val();
            if (REDIRECT_PATH != '') {
                window.location.href = REDIRECT_PATH;
            } else {
                window.location.href = '/';
            }
        });

        // Handle TOTP (Time-based One-Time Password) verification button
        btnLoginVerify.on('click', function () {

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
                async function (response) {
                    const json = await response.json();
                    if (data.REDIRECT != '') {
                        window.location.href = data.REDIRECT;
                    } else {
                        window.location.href = '/';
                    }
                },
                async function (error) {
                    const json = await error.json();
                    // Display error messages in notification
                    notify({
                        icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                        title: 'ohCRUD!',
                        titleRightText: 'now',
                        text: json.errors.join('<br/>'),
                        closeOnClick: true,
                    });
                    btnLoginVerify.removeClass('disabled');
                    btnLoginVerify.html('VERIFY');
                }
            );
        });
    }

    // If the edit page is initialized
    if (pageObject.name === 'edit') {

        // UI inputs
        let themeSelect = $$('#THEME');
        let layoutSelect = $$('#LAYOUT');
        let fileToUploadMode = '';

        // UI buttons
        let btnCMSSave = $$('#btnCMSSave');
        let btnCMSCancel = $$('#btnCMSCancel');
        let btnCMSDeleteRestore = $$('#btnCMSDeleteRestore');
        btnCMSDeleteRestore.html(btnCMSDeleteRestore.data('is-deleted') === '1' ? 'RESTORE' : 'DELETE');

        // Load themes and layouts
        loadThemes();

        // Load table list
        loadTableList();

        // Load log list
        loadLogList();

        // Initialize the text editor
        ohCrudEditor = new SimpleMDE({
            element: document.getElementById('TEXT'),
            autofocus: true,
            autosave: {
                enabled: true,
                uniqueId: __PATH__,
                delay: 1000,
            },
            autoDownloadFontAwesome: false,
            spellChecker: false,
            toolbar: [
                // Various editor toolbar options
                'bold',
                'italic',
                'strikethrough',
                'heading',
                '|',
                'code',
                'quote',
                'unordered-list',
                'ordered-list',
                '|',
                'link',
                'image',
                'table',
                'horizontal-rule',
                '|',
                {
                    name: 'upload image',
                    action: function customFunction(editor) {
                        fileToUploadMode = 'image';
                        file.click();
                    },
                    className: 'fa fa-file-image-o upload-image',
                    title: 'Image',
                },
                {
                    name: 'upload file',
                    action: function customFunction(editor) {
                        fileToUploadMode = 'file';
                        file.click();
                    },
                    className: 'fa fa-file upload-file',
                    title: 'File',
                },
                '|',
                'preview',
                'side-by-side',
                'fullscreen',
                'guide'
            ],
        });

        // Handle the save button
        btnCMSSave.on('click', function () {
            btnCMSSave.addClass('disabled');
            btnCMSSave.html(`<img class="ohcrud-loader" src="/global/images/loader.svg" />`);
            let data = {
                URL: __PATH__,
                THEME: themeSelect.val(),
                LAYOUT: layoutSelect.val(),
                TITLE: $$('#TITLE').val(),
                TEXT: ohCrudEditor.value(),
            }

            httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/pages/save/',
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
                async function (response) {
                    window.location.href = __PATH__;
                },
                async function (error) {
                    const json = await error.json();
                    notify({
                        icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                        title: 'ohCRUD!',
                        titleRightText: 'now',
                        text: json.errors.join('<br/>'),
                        closeOnClick: true,
                    });
                    btnCMSSave.removeClass('disabled');
                    btnCMSSave.html(`SAVE`);
                }
            );
        });

        // Handle the cancel button
        btnCMSCancel.on('click', function () {
            window.location.href = __PATH__;
        });

        // Handle the delete/restore button
        btnCMSDeleteRestore.on('click', function () {
            btnCMSDeleteRestore.addClass('disabled');
            btnCMSDeleteRestore.html('<img class="ohcrud-loader" src="/global/images/loader.svg" />');
            let data = {
                URL: __PATH__,
            };

            httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/pages/restoreDeletePage/',
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
                async function (response) {
                    window.location.href = __PATH__;
                },
                async function (error) {
                    const json = await error.json();
                    notify({
                        icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                        title: 'ohCRUD!',
                        titleRightText: 'now',
                        text: json.errors.join('<br/>'),
                        closeOnClick: true,
                    });
                    btnCMSDeleteRestore.removeClass('disabled');
                    btnCMSDeleteRestore.html(btnCMSDeleteRestore.data('is-deleted') === '1' ? 'RESTORE' : 'DELETE');
                }
            );
        });

        // File input change event (for uploading files)
        if (file !== null) {
            file.addEventListener('change', function () {
                let fileToUpload = file.files[0];
                let formData = new FormData();
                formData.append("0", fileToUpload);
                formData.append("CSRF", __CSRF__);
                document.querySelector(`.upload-${fileToUploadMode}`).classList = `fa fa-cog fa-spin upload-${fileToUploadMode}`;

                httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/files/upload/',
                    {
                        method: 'POST',
                        cache: 'no-cache',
                        credentials: 'same-origin',
                        body: formData
                    },
                    async function (response) {
                        const json = await response.json();
                        if (fileToUploadMode === 'image') {
                            const alt = json.data.NAME.replace(`.${json.data.TYPE}`, '');
                            insertCodeInEditor(ohCrudEditor, `![${alt}](${json.data.PATH})`);
                            document.querySelector(`.upload-image`).classList = `fa fa-file-image-o upload-image`;
                        } else {
                            insertCodeInEditor(ohCrudEditor, `[${json.data.NAME}](${json.data.PATH})`);
                            document.querySelector(`.upload-file`).classList = `fa fa-file upload-file`;
                        }
                    },
                    async function (error) {
                        const json = await error.json();
                        notify({
                            icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                            title: 'ohCRUD!',
                            titleRightText: 'now',
                            text: json.errors.join('<br/>'),
                            closeOnClick: true,
                        });
                        if (fileToUploadMode === 'image') {
                            document.querySelector(`.upload-image`).classList = `fa fa-file-image-o upload-image`;
                        } else {
                            document.querySelector(`.upload-file`).classList = `fa fa-file upload-file`;
                        }
                    },
                    true
                );
            });
        }
    }

    // If the tables page is initialized
    if (pageObject.name === 'tables') {

        let tableName = $$('#TABLE').val();

        // UI inputs
        let limitSelect = $$('#LIMIT');
        if (localStorage.getItem('limit') != null) {
            limitSelect.val(localStorage.getItem('limit'));
        }

        // UI buttons
        let btnPageNext = $$('#btnPageNext');
        let btnPagePrevious = $$('#btnPagePrevious');
        let btnFormEditSave = $$('#btnFormEditSave');
        let btnFormEditCancel = $$('#btnFormEditCancel');
        let btnNewRecord = $$('#btnNewRecord');
        let btnFormCreateSave = $$('#btnFormCreateSave');
        let btnFormCreateCancel = $$('#btnFormCreateCancel');
        let btnNewUserRecord = $$('#btnNewUserRecord');
        let btnUserFormCreateEditSave = $$('#btnUserFormCreateEditSave');
        let btnUserFormCreateEditCancel = $$('#btnUserFormCreateEditCancel');
        let btnUserFormToken = $$('#btnUserFormToken');
        let btnUserFormRefreshToken = $$('#btnUserFormRefreshToken');
        let btnUserFormTOTPQRCode = $$('#btnUserFormTOTPQRCode');
        let btnUserFormTOTPRefresh = $$('#btnUserFormTOTPRefresh');
        let btnUploadFile = $$('#btnUploadFile');
        let btnFileView = $$('#btnFileView');
        let btnTableView = $$('#btnTableView');

        // Load table list
        loadTableList();

        // Load table details
        loadTableDetails(tableName);

        // Load log list
        loadLogList();

        // Handle pagination button events
        btnPageNext.on('click', function () {
            let pageNext = parseInt(btnPageNext.data('page-next'));
            if (action === 'tables') loadTableData(tableName, pageNext);
            if (action === 'files') loadFilesData(pageNext);
        });

        btnPagePrevious.on('click', function () {
            let pagePrevious = parseInt(btnPagePrevious.data('page-previous'));
            if (action === 'tables') loadTableData(tableName, pagePrevious);
            if (action === 'files') loadFilesData(pagePrevious);
        });

        // Handle pagination limit select
        limitSelect.on('change', function() {
            let page = parseInt($$('#PAGE_CURRENT').val());
            localStorage.setItem('limit', limitSelect.val());
            if (action === 'tables') loadTableData(tableName, page);
            if (action === 'files') loadFilesData(page);
        });

        // Handle edit popup events
        btnFormEditSave.on('click', function() {
            let data = {};
            let rowKeyColumn = $$(this).data('row-key-column');
            let rowKeyValue = $$(this).data('row-key-value');
            let formRecordInputs = $$('.formRecordInput');

            formRecordInputs.forEach((formRecordInput) => {
                if (formRecordInput.type === 'checkbox') {
                    data[formRecordInput.name] = formRecordInput.checked ? 1 : 0;
                } else {
                    data[formRecordInput.name] = formRecordInput.value;
                }
            });
            saveRowData(tableName, 'update', data, rowKeyColumn, rowKeyValue);
        });

        btnFormEditCancel.on('click', function() {
            let rowKeyColumn = $$(this).data('row-key-column');
            let rowKeyValue = $$(this).data('row-key-value');
            // Undo the highlight
            $$(`.btnRecordDelete[data-row-key-value="${rowKeyValue}"]`).parent('td').parent('tr').removeClass('data-table-row-selected');
            // Close the popup
            app.popup.close('.edit-record-popup');
            // Empty the form
            setTimeout(() => {
                $$('#formEditRecord').empty();
            }, 500);
        });

        $$('.edit-record-popup').on('popup:closed', function () {
            let rowKeyValue = $$(this).data('row-key-value');
            // Undo the highlight
            $$(`.btnRecordEdit[data-row-key-value="${rowKeyValue}"]`).parent('td').parent('tr').removeClass('data-table-row-selected');
        });

        // Handle create popup events
        btnNewRecord.on('click', function() {
            // Build the create record form
            buildFormFromData(tableName ,columnDetails, 'formCreateRecord');
            // Open the popup
            app.popup.open('.create-record-popup');
            document.querySelector('.create-record-popup .page-content').scrollTop = 0;
        });

        btnFormCreateSave.on('click', function() {
            let data = {};
            let formRecordInputs = $$('.formRecordInput');

            formRecordInputs.forEach((formRecordInput) => {
                if (formRecordInput.type === 'checkbox') {
                    data[formRecordInput.name] = formRecordInput.checked ? 1 : 0;
                } else {
                    data[formRecordInput.name] = formRecordInput.value;
                }
            });
            saveRowData(tableName, 'create', data);
        });

        btnFormCreateCancel.on('click', function() {
            // Close the popup
            app.popup.close('.create-record-popup');
            // Empty the form
            setTimeout(() => {
                $$('#formCreateRecord').empty();
            }, 500);
        });

        // Handle create user popup events
        btnNewUserRecord.on('click', function() {
            // Prepare the user record form
            $$('.create-edit-user-popup .title').text('Create User');
            $$('#btnUserFormCreateEditSave').data('mode', 'create');
            $$('.formUserRecordInput').val('');
            btnUserFormToken.addClass('hidden');
            btnUserFormRefreshToken.addClass('hidden');
            btnUserFormTOTPQRCode.addClass('hidden');
            btnUserFormTOTPRefresh.addClass('hidden');
            $$('#Users-STATUS').prop('checked', false);
            $$('#Users-TOTP').prop('checked', false);
            // Open the popup
            app.popup.open('.create-edit-user-popup');
            document.querySelector('.create-edit-user-popup .page-content').scrollTop = 0;
        });

        // Handle create edit user popup events
        btnUserFormToken.on('click', function() {
            let id = btnUserFormToken.data('row-key-value');
            showUserSecrets(id, 'TOKEN');
        });

        btnUserFormRefreshToken.on('click', function() {
            let id = btnUserFormRefreshToken.data('row-key-value');
            app.dialog.confirm('Are you sure you want to refresh this user\'s API token? This will invalidate the old token and disable all API access using it.', 'Refresh API Token', () => {
                setTimeout(() => {
                    refreshUserSecrets(id, 'TOKEN');
                }, 250);
            }, null);
        });

        btnUserFormTOTPQRCode.on('click', function() {
            let id = btnUserFormTOTPQRCode.data('row-key-value');
            showUserSecrets(id, 'TOTP_SECRET');
        });

        btnUserFormTOTPRefresh.on('click', function() {
            let id = btnUserFormTOTPRefresh.data('row-key-value');
            app.dialog.confirm('Are you sure you want to refresh this user\'s TOTP secret? If TOTP is currently enabled, they won\'t be able to log in until they register a new authenticator using the new secret.', 'Refresh TOTP Secret', () => {
                setTimeout(() => {
                    refreshUserSecrets(id, 'TOTP_SECRET');

                }, 250);
            }, null);
        });

        btnUserFormCreateEditCancel.on('click', function() {
            // Close the popup
            app.popup.close('.create-edit-user-popup');
        });

        btnUserFormCreateEditSave.on('click', function() {
            let data = {};
            let id = $$(this).data('row-key-value');
            let formUserRecordInputs = $$('.formUserRecordInput');
            let mode = $$(this).data('mode');

            formUserRecordInputs.forEach((formUserRecordInput) => {
                if (formUserRecordInput.type === 'checkbox') {
                    data[formUserRecordInput.name] = formUserRecordInput.checked ? 1 : 0;
                } else {
                    data[formUserRecordInput.name] = formUserRecordInput.value;
                }
            });
            saveUserRowData(mode, data, id);
        });

        $$('.create-edit-user-popup').on('popup:closed', function () {
            let rowKeyValue = $$(this).data('row-key-value');
            // Undo the highlight
            $$(`.btnRecordEdit[data-row-key-value="${rowKeyValue}"]`).parent('td').parent('tr').removeClass('data-table-row-selected');
        });

        // Handle file view modes
        btnFileView.on('click', function() {
            let link = $$(this).data('link');
            window.location.href = link;
        });

        btnTableView.on('click', function() {
            let link = $$(this).data('link');
            window.location.href = link;
        });

        // Handle upload new file button
        btnUploadFile.on('click', function() {
            file.click();
        });

        // File input change event (for uploading files)
        if (file !== null) {
            file.addEventListener('change', function () {
                let page = parseInt($$('#PAGE_CURRENT').val());
                let fileToUpload = file.files[0];
                let formData = new FormData();
                formData.append("0", fileToUpload);
                formData.append("CSRF", __CSRF__);

                httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/files/upload/',
                    {
                        method: 'POST',
                        cache: 'no-cache',
                        credentials: 'same-origin',
                        body: formData
                    },
                    async function (response) {
                        const json = await response.json();
                        // Reload the page
                        if (getFromURL('action') === 'tables') {
                            loadTableData('Files', page);
                        }
                        if (getFromURL('action') === 'files') {
                            loadFilesData(page);
                        }
                    },
                    async function (error) {
                        const json = await error.json();
                        notify({
                            icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                            title: 'ohCRUD!',
                            titleRightText: 'now',
                            text: json.errors.join('<br/>'),
                            closeOnClick: true,
                        });
                    },
                    true
                );
            });
        }
    }

    // If the logs page is initialized
    if (pageObject.name === 'logs') {

        let logName = $$('#LOG').val();

        // UI inputs
        let limitSelect = $$('#LIMIT');
        if (localStorage.getItem('limit') != null) {
            limitSelect.val(localStorage.getItem('limit'));
        }

        // UI buttons
        let btnClearLog = $$('#btnClearLog');
        let btnPageNext = $$('#btnPageNext');
        let btnPagePrevious = $$('#btnPagePrevious');

        // Load table list
        loadTableList();

        // Load log list
        loadLogList();

        // Load log data
        loadLogData(logName, getFromURL('page'));

        // Handle pagination button events
        btnPageNext.on('click', function () {
            let pageNext = parseInt(btnPageNext.data('page-next'));
            if (action === 'logs') loadLogData(logName, pageNext);
        });

        btnPagePrevious.on('click', function () {
            let pagePrevious = parseInt(btnPagePrevious.data('page-previous'));
            if (action === 'logs') loadLogData(logName, pagePrevious);
        });

        // Handle pagination limit select
        limitSelect.on('change', function() {
            let page = parseInt($$('#PAGE_CURRENT').val());
            localStorage.setItem('limit', limitSelect.val());
            if (action === 'logs') loadLogData(logName, page);
        });

        // Handle clear logs button
        btnClearLog.on('click', function() {
            let log = $$('#LOG').val();
            app.dialog.confirm('Are you sure you want to clear this log file? This will permanently erase its contents.', 'Clear Log File', () => {
                setTimeout(() => {
                    clearLogFile(log);
                }, 250);
            }, null);
        });

        // Handle log context popup events
        $$('.context-popup').on('popup:closed', function () {
            rowKeyValue = $$(this).data('row-key-value');

            // Undo the highlight
            $$(`.btnContext[data-row-key-value="${rowKeyValue}"]`).parent('td').parent('tr').removeClass('data-table-row-selected');
        });

    }
});

// -------------------------------------------------------------------------
// Login:
// -------------------------------------------------------------------------

// Logout the user
function logout(redirect = false) {
    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/users/logout/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            )
        },
        async function (response) {
            const json = await response.json();
            if (redirect === true) {
                window.location.href = '/login/';
            }
        },
        async function (error) {
            const json = await error.json();
        }
    );
}

// -------------------------------------------------------------------------
// Edit:
// -------------------------------------------------------------------------

// Load installed themes and update the dropdown menu
function loadThemes() {

    let themeSelect = $$('#THEME');
    let layoutSelect = $$('#LAYOUT');

    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/themes/getThemes/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            )
        },
        async function (response) {
            const json = await response.json();
            themes = json.data;
            for (const theme in themes) {
                let option = document.createElement('option');
                option.value = theme;
                option.innerHTML = theme;
                themeSelect.append(option);
            }
            loadLayouts(__CURRENT_THEME__);
            themeSelect.val(__CURRENT_THEME__);
            layoutSelect.val(__CURRENT_LAYOUT__);

            // add event listener to the "select" to load layouts as theme changes
            themeSelect.on('change', function () {
                loadLayouts(themeSelect.val());
            });
        }
    );
}

// Load the layouts associated with a theme and update the dropdown menu
function loadLayouts(theme) {

    let layoutSelect = $$('#LAYOUT');
    let layout = '';
    let option = '';

    layoutSelect.empty();
    for (const layoutIndex in themes[theme]) {
        layout = themes[theme][layoutIndex];
        option = document.createElement('option');
        option.value = layout;
        option.textContent = layout;
        layoutSelect.append(option);
    }
}

// Utility function to insert a text snippet in the content editor
function insertCodeInEditor(editor, text = '', preText = '', postText = '') {
    let cm = editor.codemirror;
    let startPoint = cm.getCursor('start');
    let endPoint = cm.getCursor('end');
    let selection = cm.getSelection();
    if (text === '') { text = selection; }
    cm.replaceSelection(preText + text + postText);
    startPoint.ch += preText.length;
    if (startPoint !== endPoint) {
        endPoint.ch += postText.length;
    }
    cm.setSelection(startPoint, endPoint);
    cm.focus();
}

// -------------------------------------------------------------------------
// Tables:
// -------------------------------------------------------------------------

// Load table list
function loadTableList() {
    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/getTableList/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: {}
        },
        async function (response) {
            const json = await response.json();
            let listTables = $$('#listTables');
            listTables.empty();
            Object.entries(json.data).forEach(([table, tableData]) => {
                if (['Users', 'Pages', 'Files'].includes(table)) return;
                let listTablesItem = `
                <li>
                    <a class="item-link item-content listTablesItem" data-table-name="${tableData.NAME}" data-table-row-count="${tableData.ROW_COUNT}">
                        <div class="item-media">
                            <i class="f7-icons">cube</i>
                        </div>
                        <div class="item-inner">
                            <div class="item-title">${tableData.NAME}</div>
                        </div>
                    </a>
                </li>
            `;
                listTables.append(listTablesItem);
            });

            $$('.listTablesItem').on('click', function () {
                let tableName = $$(this).data('table-name');
                window.location.href = __PATH__ + '?action=tables&table=' + tableName;
            });
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
        }
    );
}

// Load table details
function loadTableDetails(table) {
    columnDetails = {};

    if (typeof table === undefined || table === '') return;

    // Get table details
    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/getTableDetails/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: {
                TABLE: table,
                COLUMNS: true
            }
        },
        async function (response) {
            const json = await response.json();

            if (typeof json.data[table] != undefined) {
                let tableHeader = `
                <tr>
                    <th class="checkbox-cell">
                        <label class="checkbox">
                            <input type="checkbox" /><i class="icon-checkbox"></i>
                        </label>
                    </th>
                `;

                json.data[table].COLUMNS.forEach(column => {
                    let tableHeaderTHIcon = '';
                    if (column.PRIMARY_KEY === true) {
                        column.ICON = 'fa-key';
                    }
                    tableHeaderTHIcon = '<i class="fa ' + column.ICON + '" aria-hidden="true"></i> ';
                    let tableHeaderTH = `
                    <th class="label-cell" data-detected-type="${column.DETECTED_TYPE}">
                        ${tableHeaderTHIcon}${column.NAME}
                    </th>
                    `;

                    // Check if we should render the column
                    if (isColumnVisible(table, column.NAME) === true || column.PRIMARY_KEY === true) {
                        tableHeader += tableHeaderTH;
                    }

                    // Update global variable with the column details
                    columnDetails[column.NAME] = {...column};
                });

                tableHeader += `
                    <th></th>
                </tr>
                `;

                $$('.tableHeader').empty();
                $$('.tableHeader').html(tableHeader);

                if (getFromURL('action') === 'tables') {
                    loadTableColumnToggles(table);
                    loadTableData(table, getFromURL('page'));
                }
                if (getFromURL('action') === 'files') loadFilesData(getFromURL('page'));
            }
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
        }
    );
}

// Load table column toggles
function loadTableColumnToggles(table) {

    let listColumnsToggle = $$('#listColumnsToggle');
    listColumnsToggle.empty();

    Object.entries(columnDetails).forEach(([index, column]) => {
        // Skip primary key(s)
        if (column.PRIMARY_KEY === true) return;

        // Check if column is enabled
        let columnEnabled = isColumnVisible(table, column.NAME);

        let listColumnsToggleLI = `
            <li class="item-content">
                <div class="item-inner">
                    <div class="item-title">${column.NAME}</div>
                    <div class="item-after">
                        <label class="toggle">
                            <input type="checkbox" data-column-name="${column.NAME}" class="listColumnsToggleItem" ${columnEnabled === true ? 'checked="true"' : ''}>
                            <span class="toggle-icon"></span>
                        </label>
                    </div>
                </div>
            </li>
        `;

        // Create a temporary element to hold the HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = listColumnsToggleLI.trim();

        // Append the field to the container
        listColumnsToggle.append(tempDiv.firstChild);
    });

    $$('.listColumnsToggleItem').on('click', function() {
        let columnName = $$(this).data('column-name');
        let columnEnabled = $$(this).prop('checked');
        setColumnVisibility(table, columnName, columnEnabled);

        loadTableDetails(table);
    });

}

// Load table data
function loadTableData(table, page) {

    if (page === false) {
        page = 1;
    } else {
        page = parseInt(page);
    }

    limit = parseInt($$('#LIMIT').val());
    $$('#btnFileView').data('link', '/?action=files&page=' + page);
    $$('#PAGE_CURRENT').val(page);

    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/getTableData/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: {
                TABLE: table,
                PAGE: page,
                LIMIT: limit,
            }
        },
        async function (response) {
            const json = await response.json();
            if (typeof json.data != undefined) {
                let tableBody = '';
                let tableBodyTD = '';
                let columnValue = '';
                let primaryColumnName = '';
                let primaryColumnValue = '';
                let pageURL = '';

                // Load the table data into the data table
                json.data.forEach(row => {
                    tableBody += `
                    <tr>
                        <td class="checkbox-cell">
                            <label class="checkbox">
                                <input type="checkbox" /><i class="icon-checkbox"></i>
                            </label>
                        </td>
                    `;

                    tableBodyTD = '';
                    primaryColumnName = '';
                    primaryColumnValue = '';

                    Object.entries(row).forEach(([key, value]) => {
                        columnValue = value;

                        // Get primary column key and value
                        if (columnDetails[key].PRIMARY_KEY === true) {
                            primaryColumnName = columnDetails[key].NAME;
                            primaryColumnValue = value;
                        }

                        // Special cases
                        if (table === 'Pages' && ['STATUS'].includes(columnDetails[key].NAME)) {
                            columnDetails[key].DETECTED_TYPE = 'boolean';
                        }
                        if (table === 'Users' && ['STATUS', 'TOTP'].includes(columnDetails[key].NAME)) {
                            columnDetails[key].DETECTED_TYPE = 'boolean';
                        }
                        if (table === 'Files' && ['STATUS'].includes(columnDetails[key].NAME)) {
                            columnDetails[key].DETECTED_TYPE = 'boolean';
                        }

                        // Hash and encrypted chips
                        switch (columnDetails[key].DETECTED_TYPE) {
                            case 'encrypted (bcrypt)':
                            case 'hash (MD5)':
                            case 'hash (SHA1)':
                            case 'hash (SHA256)':
                            case 'hash (unknown)':
                            case 'base64':
                            case 'encrypted (guessed)':
                                columnValue = `
                                <div class="chip">
                                    <div class="chip-label">${columnDetails[key].DETECTED_TYPE}</div>
                                </div>
                                `
                                break;
                            case 'boolean':
                                columnValue = `
                                    <div class="chip ${value === 1 ? 'color-blue' : ''}">
                                        <div class="chip-label">${value === 1 ? 'TRUE' : 'FALSE'}</div>
                                    </div>
                                `;
                                break;
                            default:
                                columnValue = value;
                                break;
                        }

                        // Null chips
                        if (value === null) {
                            columnValue = `
                                <div class="chip">
                                    <div class="chip-label">NULL</div>
                                </div>
                            `;
                        }

                        // Empty chips
                        if (value === '') {
                            columnValue = `
                                <div class="chip">
                                    <div class="chip-label">EMPTY</div>
                                </div>
                            `;
                        }

                        // Handle special cases for ohCRUD tables ('Users', 'Pages', 'Files')
                        if (table === 'Pages' && key === 'URL') {
                            pageURL = value;
                        }

                        // Check if we should render the column
                        if (isColumnVisible(table, columnDetails[key].NAME) === true || columnDetails[key].PRIMARY_KEY === true) {
                            tableBodyTD += `<td data-detected-type="${(value !== null && value !== '') ? columnDetails[key].DETECTED_TYPE : ''}">${columnValue}</td>`;
                        }
                    });

                    tableBody += tableBodyTD;
                    tableBody += `
                        <td class="actions-cell">
                            <a class="btnRecordEdit link icon-only" data-table="${table}" ${pageURL != '' ? 'data-page-url="' + pageURL + '?action=edit"' : ''} data-row-key-column="${primaryColumnName}" data-row-key-value="${primaryColumnValue}"><i class="icon f7-icons">square_pencil</i></a>
                            <a class="btnRecordDelete link icon-only" data-row-key-column="${primaryColumnName}" data-row-key-value="${primaryColumnValue}"><i class="icon f7-icons">trash</i></a>
                        </td>
                    </tr>
                    `;
                });

                $$('.tableBody').empty();
                $$('.tableBody').html(tableBody);

                // Event listener for row action buttons
                $$('.btnRecordEdit').on('click', function() {
                    let rowKeyColumn = $$(this).data('row-key-column');
                    let rowKeyValue = $$(this).data('row-key-value');
                    let rowTable = $$(this).data('table');

                    // Highlight the row
                    $$(`.btnRecordDelete[data-row-key-value="${rowKeyValue}"]`).parent('td').parent('tr').addClass('data-table-row-selected');

                    // Handle special cases for ohCRUD tables
                    switch (rowTable) {
                        case 'Pages':
                            window.location.href = $$(this).data('page-url');
                            return;

                        case 'Users':
                            // Get row data
                            loadUserRowData(rowKeyValue);
                            // Open the popup
                            $$('.create-edit-user-popup .title').text('Edit User');
                            $$('#btnUserFormCreateEditSave').data('mode', 'update');
                            $$('#btnUserFormToken').removeClass('hidden');
                            $$('#btnUserFormRefreshToken').removeClass('hidden');
                            $$('#btnUserFormTOTPQRCode').removeClass('hidden');
                            $$('#btnUserFormTOTPRefresh').removeClass('hidden');
                            $$('.create-edit-user-popup').data('row-key-value', rowKeyValue);
                            app.popup.open('.create-edit-user-popup');
                            document.querySelector('.create-edit-user-popup .page-content').scrollTop = 0;
                            break;

                        default:
                            // Get row data
                            loadRowData(table, rowKeyColumn, rowKeyValue);
                            // Open the popup
                            $$('.edit-record-popup').data('row-key-value', rowKeyValue);
                            app.popup.open('.edit-record-popup');
                            document.querySelector('.edit-record-popup .page-content').scrollTop = 0;
                        break;
                    }
                });

                $$('.btnRecordDelete').on('click', function() {
                    let rowKeyColumn = $$(this).data('row-key-column');
                    let rowKeyValue = $$(this).data('row-key-value');

                    // Highlight the row
                    $$(`.btnRecordDelete[data-row-key-value="${rowKeyValue}"]`).parent('td').parent('tr').addClass('data-table-row-selected');

                    // Open the confirmation dialog
                    setTimeout(() => {
                        app.dialog.confirm('Do you really want to delete the selected record?', 'Delete Record',
                            () => {
                                httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/deleteTableRow/',
                                    {
                                        method: 'POST',
                                        cache: 'no-cache',
                                        credentials: 'same-origin',
                                        headers: new Headers(
                                            {
                                                'Content-Type': 'application/json'
                                            }
                                        ),
                                        body: {
                                            TABLE: table,
                                            KEY_COLUMN: rowKeyColumn,
                                            KEY_VALUE: rowKeyValue,
                                        }
                                    },
                                    async function (response) {
                                        loadTableData(table, page);
                                    },
                                    async function (error) {
                                        const json = await error.json();
                                        notify({
                                            icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                                            title: 'ohCRUD!',
                                            titleRightText: 'now',
                                            text: json.errors.join('<br/>'),
                                            closeOnClick: true,
                                        });
                                    }
                                );
                            },
                            () => {
                                // Undo the highlight
                                $$(`.btnRecordDelete[data-row-key-value="${rowKeyValue}"]`).parent('td').parent('tr').removeClass('data-table-row-selected');
                            }
                        );
                    }, 250);
                });

                // Update the pagination buttons and text
                if (page > json.pagination.totalPages) page = json.pagination.totalPages;
                $$('#RECORDS_DISPLAYED').text(json.pagination.showing);
                if (json.pagination.hasNextPage === true) {
                    $$('#btnPageNext').removeClass('disabled');
                    $$('#btnPageNext').attr('data-page-next', (page + 1));
                } else {
                    $$('#btnPageNext').addClass('disabled');
                    $$('#btnPageNext').removeAttr('data-page-next');
                }
                if (json.pagination.hasPreviousPage === true) {
                    $$('#btnPagePrevious').removeClass('disabled');
                    $$('#btnPagePrevious').attr('data-page-previous', (page - 1));
                } else {
                    $$('#btnPagePrevious').addClass('disabled');
                    $$('#btnPagePrevious').removeAttr('data-page-previous');
                }
            }
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
            // Display error messages in notification
            notify({
                icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                title: 'ohCRUD!',
                titleRightText: 'now',
                text: 'Something went wrong with loading table data.',
                closeOnClick: true,
            });
        }
    );
}

// This function gets the user row data from database
function loadUserRowData(keyValue) {
    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/readTableRow/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: {
                TABLE: 'Users',
                KEY_COLUMN: 'ID',
                KEY_VALUE: keyValue,
            }
        },
        async function (response) {
            const json = await response.json();
            $$('#btnUserFormEditCancel').data('row-key-column', 'ID');
            $$('#btnUserFormEditCancel').data('row-key-value', keyValue);
            $$('#btnUserFormCreateEditSave').data('row-key-column', 'ID');
            $$('#btnUserFormCreateEditSave').data('row-key-value', keyValue);
            $$('#btnUserFormToken').data('row-key-value', keyValue);
            $$('#btnUserFormRefreshToken').data('row-key-value', keyValue);
            $$('#btnUserFormTOTPQRCode').data('row-key-value', keyValue);
            $$('#btnUserFormTOTPRefresh').data('row-key-value', keyValue);

            Object.entries(json.data).forEach(([key, value]) => {
                if (['STATUS', 'TOTP'].includes(key) === true) {
                    $$('#Users-' + key).prop('checked', value === 1 ? true : false);
                    $$('#Users-' + key).val(value);
                    return;
                }
                $$('#Users-' + key).val(value);
            });
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
            // Display error messages in notification
            notify({
                icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                title: 'ohCRUD!',
                titleRightText: 'now',
                text: 'Something went wrong with loading table row data.',
                closeOnClick: true,
            });
        }
    );
}

// This function gets the row data from database
function loadRowData(table, keyColumn, keyValue) {
    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/readTableRow/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: {
                TABLE: table,
                KEY_COLUMN: keyColumn,
                KEY_VALUE: keyValue,
            }
        },
        async function (response) {
            const json = await response.json();
            $$('#btnFormEditCancel').data('row-key-column', keyColumn);
            $$('#btnFormEditCancel').data('row-key-value', keyValue);
            $$('#btnFormEditSave').data('row-key-column', keyColumn);
            $$('#btnFormEditSave').data('row-key-value', keyValue);
            buildFormFromData(table, columnDetails, 'formEditRecord', json.data);
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
            // Display error messages in notification
            notify({
                icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                title: 'ohCRUD!',
                titleRightText: 'now',
                text: 'Something went wrong with loading table row data.',
                closeOnClick: true,
            });
        }
    );
}

// This function update a row in the table with the form data
function saveRowData(table, mode = 'update', data, keyColumn, keyValue) {

    let body = {};
    let adminAPI = '';
    let page = parseInt($$('#PAGE_CURRENT').val());

    if (mode === 'create') {
        adminAPI = 'createTableRow';

        // Remove unwanted data
        Object.entries(columnDetails).forEach(([index, column]) => {
            if (column.PRIMARY_KEY === true ||column.EXTRA === 'auto_increment') {
                delete data[column.NAME];
            }
        });

        body = {
            TABLE: table,
            ...data
        };
    }
    if (mode === 'update') {
        adminAPI = 'updateTableRow';
        body = {
            TABLE: table,
            KEY_COLUMN: keyColumn,
            KEY_VALUE: keyValue,
            ...data
        };
    }

    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/' + adminAPI + '/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: body
        },
        async function (response) {
            const json = await response.json();
            // Undo the highlight
            $$(`.btnRecordDelete[data-row-key-value="${keyValue}"]`).parent('td').parent('tr').removeClass('data-table-row-selected');

            // Reload the page
            loadTableData(table, page);

            // Close the popup
            app.popup.close('.create-record-popup');
            app.popup.close('.edit-record-popup');

            // Empty the form
            setTimeout(() => {
                if (mode === 'create') $$('#formCreateRecord').empty();
                if (mode === 'update') $$('#formEditRecord').empty();
            }, 500);
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
            // Display error messages in notification
            notify({
                icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                title: 'ohCRUD!',
                titleRightText: 'now',
                text: json.errors.join('<br/>'),
                closeOnClick: true,
            });
        }
    );
}

// This function update a user row with the form data
function saveUserRowData(mode = 'update', data, id) {

    let body = {};
    let adminAPI = '';
    let page = parseInt($$('#PAGE_CURRENT').val());

    if (mode === 'create') {
        adminAPI = 'createUserRow';

        // Remove unwanted data
        Object.entries(columnDetails).forEach(([index, column]) => {
            if (column.PRIMARY_KEY === true ||column.EXTRA === 'auto_increment') {
                delete data[column.NAME];
            }
        });

        body = {
            ...data
        };
    }
    if (mode === 'update') {
        adminAPI = 'updateUserRow';
        body = {
            ID: id,
            ...data
        };
    }

    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/' + adminAPI + '/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: body
        },
        async function (response) {
            const json = await response.json();
            // Undo the highlight
            $$(`.btnRecordDelete[data-row-key-value="${id}"]`).parent('td').parent('tr').removeClass('data-table-row-selected');

            // Reload the page
            loadTableData('Users', page);

            // Close the popup
            app.popup.close('.create-edit-user-popup');
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
            // Display error messages in notification
            notify({
                icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                title: 'ohCRUD!',
                titleRightText: 'now',
                text: json.errors.join('<br/>'),
                closeOnClick: true,
            });
        }
    );
}

// This function builds a form from column data and loads the form with data
function buildFormFromData(table, columns, elementId, rowData = {}) {
    const container = document.getElementById(elementId);
    if (!container) {
        console.error(`Element with ID '${elementId}' not found`);
        return;
    }

    // Clear existing content
    container.innerHTML = '';

    // Validation patterns and error messages for each detected type
    const validationConfig = {
        email: {
            pattern: '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$',
            errorMessage: 'Please enter a valid email address',
            inputType: 'email'
        },
        URL: {
            pattern: '^https?:\\/\\/(www\\.)?[-a-zA-Z0-9@:%._\\+~#=]{1,256}\\.[a-zA-Z0-9()]{1,6}\\b([-a-zA-Z0-9()@:%_\\+.~#?&//=]*)$',
            errorMessage: 'Please enter a valid URL starting with http:// or https://',
            inputType: 'url'
        },
        IPv4: {
            pattern: '^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$',
            errorMessage: 'Please enter a valid IPv4 address (e.g., 192.168.1.1)',
            inputType: 'text'
        },
        IPv6: {
            pattern: '^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$',
            errorMessage: 'Please enter a valid IPv6 address',
            inputType: 'text'
        },
        datetime: {
            pattern: '',
            errorMessage: 'Please enter a valid date and time',
            inputType: 'datetime-local'
        },
        date: {
            pattern: '',
            errorMessage: 'Please enter a valid date',
            inputType: 'date'
        },
        time: {
            pattern: '^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$',
            errorMessage: 'Please enter a valid time in HH:MM or HH:MM:SS format',
            inputType: 'time'
        },
        integer: {
            pattern: '^-?\\d+$',
            errorMessage: 'Please enter a valid integer number',
            inputType: 'number'
        },
        float: {
            pattern: '^-?\\d*\\.?\\d+$',
            errorMessage: 'Please enter a valid decimal number',
            inputType: 'number'
        }
    };

    // Function to get max length from column type
    function getMaxLength(columnType) {
        const match = columnType.match(/varchar\\((\\d+)\\)|char\\((\\d+)\\)/i);
        if (match) {
            return parseInt(match[1] || match[2]);
        }
        return null;
    }

    // Function to determine if field should be required
    function isRequired(column) {
        return !column.NULLABLE && column.EXTRA !== 'auto_increment';
    }

    // Function to determine if a field should be readonly
    function isReadonly(column) {
        return (column.EXTRA === 'auto_increment' || column.PRIMARY_KEY === true || ['CDATE', 'MDATE', 'CUSER', 'MUSER'].includes(column.NAME));
    }

    // Function to get field value from row data
    function getFieldValue(column, rowData) {
        const value = rowData[column.NAME];
        if (value === null || value === undefined) {
            return column.DEFAULT || '';
        }

        // Format datetime values for datetime-local input
        if (column.DETECTED_TYPE === 'datetime' && value) {
            const date = new Date(value);
            if (!isNaN(date.getTime())) {
                return value;
            }
        }

        // Format date values for date input
        if (column.DETECTED_TYPE === 'date' && value) {
            const date = new Date(value);
            if (!isNaN(date.getTime())) {
                return value;
            }
        }

        return value.toString();
    }

    // Build form fields
    Object.entries(columnDetails).forEach(([index, column]) => {

        const config = validationConfig[column.DETECTED_TYPE] || {
            pattern: '',
            errorMessage: '',
            inputType: 'text'
        };

        // Determine input type
        let inputType = config.inputType;
        if (column.DETECTED_TYPE === 'boolean') {
            inputType = 'checkbox';
        }

        // Get max length
        const maxLength = getMaxLength(column.TYPE);

        // Determine if field is required
        const required = isRequired(column);

        // Determine if field is readonly
        const readonly = isReadonly(column);

        // Get field value
        const fieldValue = getFieldValue(column, rowData);

        // Handle exceptions for ohCRUD stamp fields
        if (['CDATE', 'MDATE', 'CUSER', 'MUSER'].includes(column.NAME) && fieldValue === '') {
            inputType = 'text';
        }

        let fieldHtmlInner = `
            <div class="item-title item-label">${column.NAME}</div>
            <div class="item-input-wrap">
                <input
                    id="${table}-${column.NAME}"
                    name="${column.NAME}"
                    type="${inputType}"
                    ${maxLength ? `maxlength="${maxLength}"` : ''}
                    ${required ? 'required="true"' : ''}
                    ${readonly ? 'readonly="true"' : ''}
                    ${config.pattern ? 'validate="true"' : ''}
                    ${config.pattern ? `pattern="${config.pattern}"` : ''}
                    ${config.errorMessage ? `data-error-message="${config.errorMessage}"` : ''}
                    value="${fieldValue}"
                    spellcheck="false"
                    autocorrect="off"
                    autocapitalize="off"
                    ${inputType === 'number' ? 'step="any"' : ''}
                    class="formRecordInput"
                />
            </div>
        `;

        if (column.DETECTED_TYPE === 'boolean'){
            fieldHtmlInner = `
                <div class="item-title item-label">${column.NAME}</div>
                <div class="item-after">
                    <label class="toggle">
                        <input
                            id="${column.NAME}"
                            name="${column.NAME}"
                            type="${inputType}"
                            ${required ? 'required="true"' : ''}
                            ${readonly ? 'readonly="true"' : ''}
                            ${fieldValue === true ? 'checked="true"' : ''}
                            value="${fieldValue}"
                            class="formRecordInput"
                        />
                        <span class="toggle-icon"></span>
                    </label>
                </div>
            `;
        }

        // Create field HTML
        const fieldHtml = `
            <li>
                <div class="item-content item-input">
                    <div class="item-media">
                        <i class="fa ${column.ICON}"></i>
                    </div>
                    <div class="item-inner">
                        ${fieldHtmlInner}
                    </div>
                </div>
            </li>
        `;

        // Create a temporary element to hold the HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = fieldHtml.trim();

        // Append the field to the container
        container.appendChild(tempDiv.firstChild);
    });

    return container;
}

// This function gets user secrets from API and displays to the admins
function showUserSecrets(id, type = 'TOKEN') {

    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/getUserSecrets/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: {
                ID: id
            }
        },
        async function (response) {
            const json = await response.json();

            if (type === 'TOKEN') {
                if (json.data.TOKEN === false) {
                    // Display error messages in notification
                    notify({
                        icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                        title: 'ohCRUD!',
                        titleRightText: 'now',
                        text: 'This user does not have an API access token.',
                        closeOnClick: true,
                    });
                    return;
                }
                // Display the API access token
                notify({
                    icon: '<i class="f7-icons icon color-blue">info_circle</i>',
                    title: 'ohCRUD! - API access token',
                    text: `
                        Below is the API access token for user <strong>${json.data.USERNAME}</strong>:
                        <div class="list list-strong-ios list-dividers-ios inset-ios no-margin">
                            <div class="item-content item-input">
                                <div class="item-inner">
                                    <div class="item-input-wrap">
                                        <input id="" name="" type="text" readonly value="${json.data.TOKEN}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `,
                    closeButton: true,
                }, null);
                return;
            }

            if (type === 'TOTP_SECRET') {
                if (json.data.TOTP_SECRET === false || json.data.QR_CODE === false) {
                    // Display error messages in notification
                    notify({
                        icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                        title: 'ohCRUD!',
                        titleRightText: 'now',
                        text: 'This user does not have a TOTP secret, or it has not been enabled.',
                        closeOnClick: true,
                    });
                    return;
                }
                // Display the TOTP QR Code
                let totpQRCode = new QRCode({
                    content: json.data.QR_CODE,
                    container: 'svg-viewbox',
                    padding: 0,
                    join: true
                });
                let totpQRCodeSVG = totpQRCode.svg();

                notify({
                    icon: '<i class="f7-icons icon color-blue">info_circle</i>',
                    title: 'ohCRUD! - TOTP secret',
                    text: `
                        Below is the TOTP secret for user <strong>${json.data.USERNAME}</strong>:
                        <div class="totp-qr-code">${totpQRCodeSVG}</div>
                    `,
                    closeButton: true,
                }, null);
                return;
            }
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
            // Display error messages in notification
            notify({
                icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                title: 'ohCRUD!',
                titleRightText: 'now',
                text: 'Something went wrong with getting user secrets.',
                closeOnClick: true,
            });
        }
    );

}

// This function refreshes the user secrets
function refreshUserSecrets(id, type = 'TOKEN') {
    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/refreshUserSecrets/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: {
                ID: id,
                SECRET_TYPE: type
            }
        },
        async function (response) {
            const json = await response.json();
            // Prepare message
            let message = '';
            if (type === 'TOKEN') message = 'API access token has been refreshed.';
            if (type === 'TOTP_SECRET') message = 'TOTP secret has been refreshed.';
            // Display success message
            notify({
                icon: '<i class="f7-icons icon color-blue">info_circle</i>',
                title: 'ohCRUD! - Success',
                titleRightText: 'now',
                text: message,
                closeOnClick: true,
            });
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
            // Display error messages in notification
            notify({
                icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                title: 'ohCRUD!',
                titleRightText: 'now',
                text: 'Something went wrong with refreshing user secrets.',
                closeOnClick: true,
            });
        }
    );

}

// -------------------------------------------------------------------------
// Files:
// -------------------------------------------------------------------------

// Load files data
function loadFilesData(page) {

    if (page === false) {
        page = 1;
    } else {
        page = parseInt(page);
    }

    let fileIcons = {'CSV': 'table', 'TXT': 'doc_text', 'PDF': 'doc_richtext', 'XML': 'doc_text', 'XLXS': 'doc_chart', 'JSON': 'doc', 'ZIP': 'archivebox', 'MP3': 'music_note_2'};
    limit = parseInt($$('#LIMIT').val());
    $$('#btnTableView').data('link', '/?action=tables&table=Files&page=' + page);
    $$('#PAGE_CURRENT').val(page);

    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/getTableData/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: {
                TABLE: 'Files',
                PAGE: page,
                LIMIT: limit,
            }
        },
        async function (response) {
            const json = await response.json();
            if (typeof json.data != undefined) {

                let fileType = '';
                let fileCards = '';

                // Load the table data into the data table
                json.data.forEach(row => {

                    // Skip bad data
                    if (row.PATH === undefined || row.PATH === null ||
                        row.NAME === undefined || row.NAME === null ||
                        row.SIZE === undefined || row.SIZE === null ||
                        row.TYPE === undefined || row.TYPE === null) {
                        return;
                    }

                    fileType = row.TYPE.toUpperCase();
                    if (['CSV', 'TXT', 'PDF', 'XML', 'XLXS', 'JSON', 'ZIP', 'MP3'].includes(fileType) === true) {
                        fileCards += `
                        <div class="card card-outline-md file-card">
                            <div valign="bottom" class="card-header file-card-header file-card-header-background">
                                <span class="f7-icons file-icon">${fileIcons[fileType]}</span>
                                <div class="chip file-card-chip">
                                    <div class="chip-label">${formatBytesForDocuments(row.SIZE)}</div>
                                </div>
                                <div class="chip file-card-chip">
                                    <div class="chip-label">${fileType}</div>
                                </div>
                            </div>
                            <div class="card-content card-content-padding">
                                <p>${row.NAME}<br/>
                                    <small>${row.CDATE}</small>
                                </p>
                            </div>
                            <div class="card-footer">
                                <a class="link external f7-icons" href="${row.PATH}" target="_blank">link</a>
                                <a class="link f7-icons" onclick="copyToClipboard('[${row.NAME}](${row.PATH})', true)">square_on_square</a>
                            </div>
                        </div>
                        `;
                    } else {
                        fileCards += `
                        <div class="card card-outline-md file-card">
                            <div style="background-image:url(${row.PATH}?w=400)" valign="bottom" class="card-header file-card-header">
                                <div class="chip file-card-chip">
                                    <div class="chip-label">${formatBytesForDocuments(row.SIZE)}</div>
                                </div>
                                <div class="chip file-card-chip">
                                    <div class="chip-label">${fileType}</div>
                                </div>
                            </div>
                            <div class="card-content card-content-padding">
                                <p>${row.NAME}<br/>
                                    <small>${row.CDATE}</small>
                                </p>
                            </div>
                            <div class="card-footer">
                                <a class="link external f7-icons" href="${row.PATH}" target="_blank">link</a>
                                <a class="link f7-icons" onclick="copyToClipboard('![${row.NAME}](${row.PATH})', true)">square_on_square</a>
                            </div>
                        </div>
                        `;
                    }
                });

                $$('.fileCards').empty();
                $$('.fileCards').html(fileCards);

                // Update the pagination buttons and text
                if (page > json.pagination.totalPages) page = json.pagination.totalPages;
                $$('#RECORDS_DISPLAYED').text(json.pagination.showing);
                if (json.pagination.hasNextPage === true) {
                    $$('#btnPageNext').removeClass('disabled');
                    $$('#btnPageNext').attr('data-page-next', (page + 1));
                } else {
                    $$('#btnPageNext').addClass('disabled');
                    $$('#btnPageNext').removeAttr('data-page-next');
                }
                if (json.pagination.hasPreviousPage === true) {
                    $$('#btnPagePrevious').removeClass('disabled');
                    $$('#btnPagePrevious').attr('data-page-previous', (page - 1));
                } else {
                    $$('#btnPagePrevious').addClass('disabled');
                    $$('#btnPagePrevious').removeAttr('data-page-previous');
                }
            }
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
            // Display error messages in notification
            notify({
                icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                title: 'ohCRUD!',
                titleRightText: 'now',
                text: 'Something went wrong with loading table data.',
                closeOnClick: true,
            });
        }
    );
}

// -------------------------------------------------------------------------
// Logs:
// -------------------------------------------------------------------------

// Load log list
function loadLogList() {
    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/getLogList/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: {}
        },
        async function (response) {
            const json = await response.json();
            let listLogs = $$('#listLogs');
            listLogs.empty();
            Object.entries(json.data).forEach(([log, logData]) => {
                // if (['Users', 'Pages', 'Files'].includes(table)) return;
                let listLogItem = `
                <li>
                    <a class="item-link item-content listLogItem" data-log-name="${logData.NAME}" data-log-path="${logData.PATH}">
                        <div class="item-media">
                            <i class="f7-icons">doc_text_search</i>
                        </div>
                        <div class="item-inner">
                            <div class="item-title">${logData.NAME}</div>
                        </div>
                    </a>
                </li>
            `;
                listLogs.append(listLogItem);
            });

            $$('.listLogItem').on('click', function () {
                let logName = $$(this).data('log-name');
                window.location.href = __PATH__ + '?action=logs&log=' + logName;
            });
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
        }
    );
}

// Load log data
function loadLogData(log, page) {
    if (page === false) {
        page = 1;
    } else {
        page = parseInt(page);
    }

    limit = parseInt($$('#LIMIT').val());
    $$('#PAGE_CURRENT').val(page);

    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/getLogData/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: {
                LOG: log,
                PAGE: page,
                LIMIT: limit,
            }
        },
        async function (response) {
            const json = await response.json();
            if (typeof json.data != undefined) {
                let tableBody = '';
                let level = '';
                let extra = '';
                let context = '';
                let hasContext = false;
                let keyValue = 0;

                json.data.forEach(row => {

                    // Generate random key value for the row
                    keyValue = Math.floor(Math.random() * 1000);

                    // Format the level
                    switch (row.level_name.toUpperCase()) {
                        case 'DEBUG':
                            level = `
                                <div class="chip color-blue">
                                    <div class="chip-label">${row.level_name}</div>
                                </div>
                            `;
                            break;
                        case 'INFO':
                            level = `
                                <div class="chip color-green">
                                    <div class="chip-label">${row.level_name}</div>
                                </div>
                            `;
                            break;
                        case 'WARNING':
                            level = `
                                <div class="chip color-orange">
                                    <div class="chip-label">${row.level_name}</div>
                                </div>
                            `;
                            break;
                        case 'ERROR':
                        case 'CRITICAL':
                            level = `
                                <div class="chip color-red">
                                    <div class="chip-label">${row.level_name}</div>
                                </div>
                            `;
                            break;
                        default:
                            level = `
                                <div class="chip">
                                    <div class="chip-label">${row.level_name}</div>
                                </div>
                            `;
                    }

                    // Format the extra data
                    if (row.extra === null || row.extra === '' || row.extra.length === 0) {
                        extra = `
                            <div class="chip">
                                <div class="chip-label">EMPTY</div>
                            </div>
                        `;
                    } else {
                        extra = `
                            <div class="chip color-blue">
                                <div class="chip-label">YES</div>
                            </div>
                        `;
                    }

                    // Format the context data
                    if (row.context === null || row.context === '' || row.context.length === 0) {
                        context = `
                            <div class="chip">
                                <div class="chip-label">EMPTY</div>
                            </div>
                        `;
                        hasContext = false;
                    } else {
                        context = `
                            <div class="chip color-blue">
                                <div class="chip-label">YES</div>
                            </div>
                        `;
                        hasContext = true;
                        row.context = window.btoa(JSON.stringify(row.context));
                        row.extra = window.btoa(JSON.stringify(row.extra));
                    }

                    // Build the table row
                    tableBody += `
                    <tr>
                        <td class="checkbox-cell">
                            <label class="checkbox">
                                <input type="checkbox" /><i class="icon-checkbox"></i>
                            </label>
                        </td>
                        <td>${level}</td>
                        <td>${row.channel}</td>
                        <td data-detected-type="message">${row.message}</td>
                        <td>${extra}</td>
                        <td>${context}</td>
                        <td data-detected-type="datetime">${row.datetime.date}</td>

                        <td class="actions-cell">
                            <a class="btnContext link icon-only ${hasContext == true ? '' : 'disabled'}" data-row-key-value="${keyValue}" data-level="${window.btoa(level)}" data-message="${window.btoa(row.message)}" data-context="${row.context}" data-extra="${row.extra}"><i class="icon f7-icons">square_stack_3d_up</i></a>
                        </td>
                    </tr>
                    `;
                });

                $$('.tableBody').empty();
                $$('.tableBody').html(tableBody);

                // Event listener for row action buttons
                $$('.btnContext').on('click', function() {
                    let rowKeyValue = $$(this).data('row-key-value');
                    let level = $$(this).data('level');
                    let message = $$(this).data('message');
                    let context = $$(this).data('context');
                    let extra = $$(this).data('extra');

                    // Highlight the row
                    $$(`.btnContext[data-row-key-value="${rowKeyValue}"]`).parent('td').parent('tr').addClass('data-table-row-selected');

                    // Display the context popup
                    displayContextPopup(rowKeyValue, level, message, context, extra);
                });

                // Update the pagination buttons and text
                if (page > json.pagination.totalPages) page = json.pagination.totalPages;
                $$('#RECORDS_DISPLAYED').text(json.pagination.showing);
                if (json.pagination.hasNextPage === true) {
                    $$('#btnPageNext').removeClass('disabled');
                    $$('#btnPageNext').attr('data-page-next', (page + 1));
                } else {
                    $$('#btnPageNext').addClass('disabled');
                    $$('#btnPageNext').removeAttr('data-page-next');
                }
                if (json.pagination.hasPreviousPage === true) {
                    $$('#btnPagePrevious').removeClass('disabled');
                    $$('#btnPagePrevious').attr('data-page-previous', (page - 1));
                } else {
                    $$('#btnPagePrevious').addClass('disabled');
                    $$('#btnPagePrevious').removeAttr('data-page-previous');
                }
            }
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
            // Display error messages in notification
            notify({
                icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                title: 'ohCRUD!',
                titleRightText: 'now',
                text: 'Something went wrong with loading log data.',
                closeOnClick: true,
            });
        }
    );
}

// This function displays the context popup with the provided context and extra data
function displayContextPopup(rowKeyValue, level, message, context, extra) {

    let levelDecoded = window.atob(level);
    let messageDecoded = window.atob(message);
    let contextDecoded = JSON.parse(window.atob(context));
    let extraDecoded = JSON.parse(window.atob(extra));

    $$('#logHeader').empty();
    $$('#logHeader').html(`
        ${levelDecoded}
        <p>${escapeHtml(messageDecoded)}</p>
        `
    );

    let className = '';
    let typeName = '';
    let functionName = '';
    let fileName = '';
    let lineNumber = '';
    let contextLength = contextDecoded.length;
    let contextHTML = '';
    contextDecoded.forEach((item, index) => {
        className = '';
        typeName = '';
        functionName = '';
        fileName = '';
        lineNumber = '';

        if (item.file !== undefined && item.file !== null && item.line !== undefined && item.line !== null) {
            className = item.class || '';
            typeName = item.type || '';
            functionName = item.function || '';
            fileName = item.file;
            lineNumber = item.line;
        }

        if (item.function !== undefined && item.function !== null && item.args !== undefined && item.args !== null && Array.isArray(item.args) === true) {
            className = item.class || '';
            typeName = item.type || '';
            functionName = item.function;
            fileName = item.args[0].file || '';
            lineNumber = '';
        }

        if (className === '' && typeName === '' && functionName === '') {
            contextHTML += `
                <li>
                    <div class="item-content">
                        <div class="item-media"><i class="icon">&nbsp;</i></div>
                        <div class="item-inner">
                            <div class="item-title monospace">
                                ${escapeHtml(JSON.stringify(item))}
                            </div>
                        </div>
                    </div>
                </li>
            `;
        } else {
            contextHTML += `
                <li>
                    <div class="item-content">
                        <div class="item-media"><i class="icon">${contextLength - index}</i></div>
                        <div class="item-inner">
                            <div class="item-title">
                                <div class="item-header">${escapeHtml(fileName)}${lineNumber !== '' ? ':' + escapeHtml(lineNumber) : ''}</div>
                                ${escapeHtml(className)}${escapeHtml(typeName)}${escapeHtml(functionName)}
                            </div>
                        </div>
                    </div>
                </li>
            `;
        }
    });
    $$('#logContext').empty();
    $$('#logContext').html(contextHTML);

    $$('#logExtra').empty();
    if (extraDecoded !== null && Object.keys(extraDecoded).length > 0) {
        $$('#logExtra').html(escapeHtml(JSON.stringify(extraDecoded, null, 2)));
    }

    $$('.context-popup').data('row-key-value', rowKeyValue);
    app.popup.open('.context-popup');
}

// Clear log file
function clearLogFile(log) {
    httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/admin/clearLog/',
        {
            method: 'POST',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: new Headers(
                {
                    'Content-Type': 'application/json'
                }
            ),
            body: {
                LOG: log
            }
        },
        async function (response) {
            const json = await response.json();
            // Display success message
            notify({
                icon: '<i class="f7-icons icon color-blue">info_circle</i>',
                title: 'ohCRUD! - Success',
                titleRightText: 'now',
                text: 'Log file <b>' + log + '</b> has been cleared successfully.',
                closeOnClick: true,
            });
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
            // Display error messages in notification
            notify({
                icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
                title: 'ohCRUD!',
                titleRightText: 'now',
                text: json.errors.join('<br/>'),
                closeOnClick: true,
            });
        }
    );
}

// -------------------------------------------------------------------------
// Misc:
// -------------------------------------------------------------------------

// This method creates and issues a Framework7 notification
function notify(options = {}, closeTimeout = 3000) {
    options.closeTimeout = closeTimeout;
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

// This function set the app dark/light mode theme
function setDarkMode(appDarkMode) {
    if (app == null) {
        setTimeout(() => {
            setDarkMode(appDarkMode);
        }, 50);
        return;
    }
    if (appDarkMode === 'Y') {
        app.setDarkMode(true);
        app.setColorTheme('#2564aa');
    } else {
        app.setDarkMode(false);
        app.setColorTheme('#007aff');
    }
    localStorage.setItem('app:dark-mode', appDarkMode);
}

// This function sets column visibility settings for a given table and column
function setColumnVisibility(tableName, columnName, isVisible) {

    const storageKey = `columns:${tableName}`;
    const storedString = localStorage.getItem(storageKey);
    let columns = {};

    // Parse existing settings if they exist
    if (storedString) {
        const match = storedString.match(/\((.*)\)/);
        if (match && match[1]) {
            match[1].split(',').forEach(setting => {
                const parts = setting.split(':');
                if (parts.length === 2) {
                    columns[parts[0].trim()] = parts[1].trim() === 'Y';
                }
            });
        }
    }

    // Update the specific column's visibility
    columns[columnName.replace(/[(),:]/g, '')] = isVisible; // Clean column name

    // Convert the updated columns object back to the desired string format
    const columnEntries = Object.entries(columns)
        .map(([colName, colIsVisible]) => `${colName}:${colIsVisible ? 'Y' : 'N'}`)
        .join(',');

    const newStorageString = `${tableName}(${columnEntries})`;
    localStorage.setItem(storageKey, newStorageString);
}

// This function gets column visibility settings for a given table and column
function isColumnVisible(tableName, columnName) {

    const storedString = localStorage.getItem(`columns:${tableName}`);

    if (!storedString) return true; // No settings, default to visible

    const match = storedString.match(/\((.*)\)/);
    if (!match || !match[1]) return true; // Malformed string, default to visible

    const columnSettingsString = match[1];
    const settingsArray = columnSettingsString.split(',');

    for (const setting of settingsArray) {
        const parts = setting.split(':');
        if (parts.length === 2) {
            const storedColumnName = parts[0].trim();
            const visibilityChar = parts[1].trim();

            if (storedColumnName === columnName) {
                return visibilityChar === 'Y';
            }
        }
    }
    // Column not found, default to visible
    return true;
}

// This function converts a size in bytes to a human-readable string (e.g., 300 KB, 1.2 MB)
function formatBytesForDocuments(bytes, precision = 1) {
    bytes = Math.max(bytes, 0); // Ensure bytes is not negative

    const ONE_MB = 1024 * 1024;
    const ONE_KB = 1024;

    let value;
    let unit;

    // Determine the appropriate unit and calculate the value
    if (bytes < ONE_MB) {
        // For bytes less than 1 MB, display in KB
        if (bytes === 0) {
            value = 0;
            unit = 'KB';
        } else {
            value = bytes / ONE_KB;
            unit = 'KB';
        }
    } else {
        // For 1 MB or more, display in MB
        value = bytes / ONE_MB;
        unit = 'MB';
    }

    // Format the value:
    // 1. Use toFixed(precision) to control decimal places.
    // 2. Use parseFloat() to convert back to a number, which inherently removes trailing zeros (e.g., 1024.00 becomes 1024).
    // 3. Convert back to a string for concatenation.
    const formattedValue = parseFloat(value.toFixed(precision)).toString();

    return `${formattedValue} ${unit}`;
}

// Retrieves the value of the 'action' URL parameter from the current page's URL.
function getFromURL(verb) {
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    const verbValue = urlParams.get(verb);

    return verbValue !== null ? verbValue : false;
}

// This function copies a given text into user's clipboard and shows a notification
function copyToClipboard(text, showNotification = false) {
    navigator.clipboard.writeText(text);
    if (showNotification === true) {
        // Display error messages in notification
        notify({
            icon: '<i class="f7-icons icon color-blue">info_circle</i>',
            title: 'ohCRUD!',
            titleRightText: 'now',
            text: `
                The content below has been copied to your clipboard:
                <div class="list list-strong-ios list-dividers-ios inset-ios no-margin">
                    <div class="item-content item-input">
                        <div class="item-inner">
                            <div class="item-input-wrap">
                                <input id="" name="" type="text" readonly value="${text}">
                            </div>
                        </div>
                    </div>
                </div>
            `,
            closeOnClick: true,
        });
    }
}

// This function escapes HTML special characters in a string to prevent XSS attacks
function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return unsafe
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}