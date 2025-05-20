// Framework7 app instance
var app = null;
// Assign Dom7 to $$ for easier DOM manipulation
var $$ = Dom7;
// global variables
let themes = {};
let ohCrudEditor = null;
const __CURRENT_THEME__ = $$('#currentTHEME').val();
const __CURRENT_LAYOUT__ = $$('#currentLAYOUT').val();

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
    if (page.name == 'login') {

        // Logout user
        logout();

        // Bring inputs to focus
        $$('#USERNAME').focus();

        // UI buttons
        let btnLogin = $$('#btnLogin');
        let btnLoginCancel = $$('#btnLoginCancel');
        let btnLoginVerify = $$('#btnLoginVerify');

        // UI Inputs
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
                async function (error) {
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

    // If the admin page is initialized
    if (page.name == 'edit') {

        setTimeout(() => {
            app.panel.open();
        }, 500);

        let themeSelect = $$('#THEME');
        let layoutSelect = $$('#LAYOUT');
        let file = document.getElementById('FILE');
        let fileToUploadMode = '';

        let btnCMSSave = $$('#btnCMSSave');
        let btnCMSCancel = $$('#btnCMSCancel');
        let btnCMSDeleteRestore = $$('#btnCMSDeleteRestore');
        btnCMSDeleteRestore.html(btnCMSDeleteRestore.data('is-deleted') == '1' ? 'RESTORE' : 'DELETE');

        // Load themes and layouts
        loadThemes();

        // Load table list
        loadTableList();

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
                        text: json.errors.join(),
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
                        text: json.errors.join(),
                        closeOnClick: true,
                    });
                    btnCMSDeleteRestore.removeClass('disabled');
                    btnCMSDeleteRestore.html(btnCMSDeleteRestore.data('is-deleted') == '1' ? 'RESTORE' : 'DELETE');
                }
            );
        });

        // File input change event (for uploading files)
        file.addEventListener('change', function () {
            let fileToUpload = file.files[0];
            let formData = new FormData();
            formData.append("0", fileToUpload);
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
                    if (fileToUploadMode == 'image') {
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
                        text: json.errors.join(),
                        closeOnClick: true,
                    });
                    if (fileToUploadMode == 'image') {
                        document.querySelector(`.upload-image`).classList = `fa fa-file-image-o upload-image`;
                    } else {
                        document.querySelector(`.upload-file`).classList = `fa fa-file upload-file`;
                    }
                },
                true
            );
        });
    }

    // If the admin page is initialized
    if (page.name == 'tables') {

        setTimeout(() => {
            app.panel.open();
        }, 500);

        let tableName = $$('#TABLE').val();

        // Load table list
        loadTableList();

        // Load table details
        loadTableDetails(tableName);

    }

});

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
            )
        },
        async function (response) {
            const json = await response.json();
            let listTables = $$('.listTables');
            listTables.empty();
            Object.entries(json.data).forEach(([table, tableData]) => {
                if (table == 'Users') return;
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
                console.log(tableName);
                window.location.href = __PATH__ + '?action=tables&table=' + tableName;
            });
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
        });
}

// Load table details
function loadTableDetails(table) {
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
                "TABLE": table,
                "COLUMNS": true
            }
        },
        async function (response) {
            const json = await response.json();

            if (typeof json.data[table] != undefined) {
                // console.log(json.data[table]);

                let tableHeader = `
                <tr>
                    <th class="checkbox-cell">
                        <label class="checkbox">
                            <input type="checkbox" /><i class="icon-checkbox"></i>
                        </label>
                    </th>
                `;

                let tableBody = `
                <tr>
                    <td class="checkbox-cell">
                        <label class="checkbox">
                            <input type="checkbox" /><i class="icon-checkbox"></i>
                        </label>
                    </td>
                `;

                json.data[table].COLUMNS.forEach(column => {
                    let tableHeaderTH = `<th class="${checkDataType(column.TYPE) == 'text' ? 'label-cell' : 'numeric-cell'}">${column.NAME}</th>`;
                    tableHeader += tableHeaderTH;

                    let tableBodyTD = `<td class="${checkDataType(column.TYPE) == 'text' ? 'label-cell' : 'numeric-cell'}">${placeholder(checkDataType(column.TYPE))}</td>`;
                    tableBody += tableBodyTD;
                });
                tableHeader += `
                    <th></th>
                </tr>
                `;
                $$('.tableHeader').empty();
                $$('.tableHeader').html(tableHeader);


                tableBody += `
                    <td class="actions-cell">
                        <a class="link icon-only"><i class="icon f7-icons">square_pencil</i></a>
                        <a class="link icon-only"><i class="icon f7-icons">trash</i></a>
                    </td>
                </tr>
                `;

                $$('.tableBody').empty();
                $$('.tableBody').html(tableBody + tableBody + tableBody + tableBody + tableBody + tableBody + tableBody + tableBody);
            }
        },
        async function (error) {
            const json = await error.json();
            console.error(json);
        });
}

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
        });
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

// Logout the user
function logout() {
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
        },
        async function (error) {
            const json = await error.json();
        }
    );
}

// This method creates and issues a Framework7 notification
function notify(options = {}) {
    let notificationCloseOnClick = app.notification.create(options);
    notificationCloseOnClick.open();
}

// Utility function to insert a text snippet in the content editor
function insertCodeInEditor(editor, text = '', preText = '', postText = '') {
    let cm = editor.codemirror;
    let startPoint = cm.getCursor('start');
    let endPoint = cm.getCursor('end');
    let selection = cm.getSelection();
    if (text == '') { text = selection; }
    cm.replaceSelection(preText + text + postText);
    startPoint.ch += preText.length;
    if (startPoint !== endPoint) {
        endPoint.ch += postText.length;
    }
    cm.setSelection(startPoint, endPoint);
    cm.focus();
}

// This function masks input number.
function maskInputNumber(input) {
    let value = input.value;
    // Allow only valid numeric characters
    value = value.replace(/[^0-9]/g, '');
    // Update the input value
    input.value = value;
}

function checkDataType(typeString) {
    if (typeof typeString !== 'string') {
        return "text"; // Or handle as an error, depending on requirements
    }

    // Normalize the type string by taking the part before any parenthesis and converting to lowercase
    const normalizedType = typeString.split('(')[0].toLowerCase().trim();

    // Common numeric types for MySQL and SQLite
    const numericTypes = [
        // MySQL & SQLite Integer Types
        "int",
        "integer",
        "tinyint",
        "smallint",
        "mediumint",
        "bigint",
        // MySQL Specific Integer Suffix (though often part of the base type like 'int unsigned')
        // We are primarily checking the base type.
        // SQLite specific integer types (often aliases)
        "int2", // SQLite alias for SMALLINT
        "int8", // SQLite alias for BIGINT

        // MySQL & SQLite Floating-Point Types
        "float",
        "double",
        "real", // SQLite, MySQL (alias for DOUBLE)

        // MySQL & SQLite Fixed-Point Types
        "decimal",
        "dec",     // MySQL alias for DECIMAL
        "numeric", // MySQL (alias for DECIMAL), SQLite

        // MySQL & SQLite Boolean (often stored as TINYINT(1))
        // Already covered by tinyint, but good to be aware
        "boolean", // MySQL alias for TINYINT(1)
        "bool"     // MySQL alias for TINYINT(1)
    ];

    if (numericTypes.includes(normalizedType)) {
        return "numeric";
    }

    // Add other specific checks if needed, e.g., for bit
    if (normalizedType === "bit") {
        return "numeric"; // BIT can be treated as numeric in many contexts
    }

    return "text";
}

function placeholder(type) {
    if (type == 'text') return 'Lorem';
    if (type == 'numeric') return parseInt(Math.random() * 100);
}