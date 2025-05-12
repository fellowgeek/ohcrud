// Define variables to handle content editor

// URL
let url = select('#url').value;
// Themes & layouts
let themes = {};
let themeSelect = select('#theme');
let layoutSelect = select('#layout');
let currentTheme = select('#current-theme').value;
let currentLayout = select('#current-layout').value;
// File
let file = select('#ohcrud-file');
let fileToUploadMode = '';
// Editor
let ohCrudEditor = {};
// Save button
let editorSave = select('#ohcrud-editor-save');
// Cancel button
let editorCancel = select('#ohcrud-editor-cancel');
// Delete / Restore button
let editorDeleteRestore = select('#ohcrud-editor-delete-restore');
editorDeleteRestore.innerHTML = editorDeleteRestore.dataset['is-deleted'] == '1' ? 'Restore' : 'Delete';

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
            themeSelect.appendChild(option);
        }
        loadLayouts(currentTheme);
        themeSelect.value = currentTheme;
        layoutSelect.value = currentLayout;
        themeSelect.addEventListener('change', function() {
            loadLayouts(themeSelect.value);
        });
    });
}

// Load the layouts associated with a theme and update the dropdown menu
function loadLayouts(theme) {
    let options = layoutSelect.getElementsByTagName('option');
    while(options.length > 0) {
        layoutSelect.remove(0);
    }
    for (const layoutIndex in themes[theme]) {
        let layout = themes[theme][layoutIndex];
        let option = document.createElement('option');
        option.value = layout;
        option.innerHTML = layout;
        layoutSelect.appendChild(option);
    }
}

// Execute the following code when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {

    // Load themes and layouts
    loadThemes();

    // Initialize the text editor
    ohCrudEditor = new SimpleMDE(
        {
            element: select('#editor-text'),
            autofocus: true,
            autosave: {
                enabled: true,
                uniqueId: url,
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
    editorSave.addEventListener('click', function() {
        editorSave.disabled = true;
        editorSave.classList.add('btn-disabled');
        editorSave.innerHTML = `<img class="ohcrud-loader" src="/global-assets/images/loader.svg" />`;
        let data = Object.fromEntries(new FormData(document.getElementById('ohcrud-editor')).entries());
        data.TEXT = ohCrudEditor.value();

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
                select('.alert').classList.add('hidden');
                window.location.href = url;
            },
            async function(error) {
                const json = await error.json();
                select('.alert').innerHTML = `<p>${json.errors.join()}</p>`;
                select('.alert').classList.add('alert-danger');
                select('.alert').classList.remove('hidden');
                editorSave.disabled = false;
                editorSave.classList.remove('btn-disabled');
                editorSave.innerHTML = `Save`;
            }
        );
    });

    // Handle the cancel button
    editorCancel.addEventListener('click', function() {
        window.location.href = url;
    });

    // Handle the delete/restore button
    editorDeleteRestore.addEventListener('click', function() {
        editorDeleteRestore.disabled = true;
        editorDeleteRestore.classList.add('btn-disabled');
        editorDeleteRestore.innerHTML = `<img class="ohcrud-loader" src="/global-assets/images/loader.svg" />`;
        let data = {
            URL: url,
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
                select('.alert').classList.add('hidden');
                window.location.href = url;
            },
            async function(error) {
                const json = await error.json();
                select('.alert').innerHTML = `<p>${json.errors.join()}</p>`;
                select('.alert').classList.add('alert-danger');
                select('.alert').classList.remove('hidden');
                editorDeleteRestore.disabled = false;
                editorDeleteRestore.classList.remove('btn-disabled');
                editorDeleteRestore.innerHTML = editorDeleteRestore.dataset['is-deleted'] == '1' ? 'Restore' : 'Delete';
            }
        );
    });

    // File input change event (for uploading files)
    file.addEventListener('change', function() {
        let fileToUpload = file.files[0];
        let formData = new FormData();
        formData.append("0", fileToUpload);
        select(`.upload-${fileToUploadMode}`).classList = `fa fa-cog fa-spin upload-${fileToUploadMode}`;

        httpRequest(__OHCRUD_BASE_API_ROUTE__ + '/files/upload/',
            {
                method: 'POST',
                cache: 'no-cache',
                credentials: 'same-origin',
                body: formData
            },
            async function(response) {
                select('.alert').classList.add('hidden');
                const json = await response.json();
                if (fileToUploadMode == 'image') {
                    const alt = json.data.NAME.replace(`.${json.data.TYPE}`, '') ;
                    insertCodeInEditor(ohCrudEditor, `![${alt}](${json.data.PATH})`);
                    select(`.upload-${fileToUploadMode}`).classList = `fa fa-file-image-o upload-${fileToUploadMode}`;
                } else {
                    insertCodeInEditor(ohCrudEditor, `[${json.data.NAME}](${json.data.PATH})`);
                    select(`.upload-${fileToUploadMode}`).classList = `fa fa-file upload-${fileToUploadMode}`;
                }
            },
            async function(error) {
                const json = await error.json();
                select('.alert').innerHTML = `<p>${json.errors.join()}</p>`;
                select('.alert').classList.add('alert-danger');
                select('.alert').classList.remove('hidden');
            },
            true
        );
    });

});