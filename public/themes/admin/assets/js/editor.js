// global variables
let themes = {};
const __CURRENT_THEME__ = $$('#currentTHEME').val();
const __CURRENT_LAYOUT__ = $$('#currentLAYOUT').val();
let ohCrudEditor = null;

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
    async function(response) {
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
        layoutSelect.val(__CURRENT_LAYOUT__)

        // add event listener to the "select" to load layouts as theme changes
        themeSelect.on('change', function() {
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

// Execute the following code when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {

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

    // Initialize the text editor
    ohCrudEditor = new SimpleMDE(
        {
            element: select('#TEXT'),
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
        }
    );

    // Handle the save button
    btnCMSSave.on('click', function() {
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
            async function(response) {
                window.location.href = __PATH__;
            },
            async function(error) {
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
    btnCMSCancel.on('click', function() {
        window.location.href = __PATH__;
    });

    // Handle the delete/restore button
    btnCMSDeleteRestore.on('click', function() {
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
            async function(response) {
                window.location.href = __PATH__;
            },
            async function(error) {
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
    file.addEventListener('change', function() {
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
            async function(response) {
                const json = await response.json();
                if (fileToUploadMode == 'image') {
                    const alt = json.data.NAME.replace(`.${json.data.TYPE}`, '') ;
                    insertCodeInEditor(ohCrudEditor, `![${alt}](${json.data.PATH})`);
                    document.querySelector(`.upload-image`).classList = `fa fa-file-image-o upload-image`;
                } else {
                    insertCodeInEditor(ohCrudEditor, `[${json.data.NAME}](${json.data.PATH})`);
                    document.querySelector(`.upload-file`).classList = `fa fa-file upload-file`;
                }
            },
            async function(error) {
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

});