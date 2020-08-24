/*
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
GLOBAL VARIABLES
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
*/

// url
var $url = $$('#ohcrud-page-url').value;

// themes & layouts
var $themes = JSON.parse(window.atob($$('#ohcrud-themes').value));
var $themeSelect = $$('#ohcrud-page-theme');
var $layoutSelect = $$('#ohcrud-page-layout');
var $currentTheme = $$('#ohcrud-page-current-theme').value;
var $currentLayout = $$('#ohcrud-page-current-layout').value;

// file
var $file = $$('#ohcrud-file');
var $fileToUploadMode = '';

// editor
var $ohCrudEditor = {};

// save
var $editorSave = $$('#ohcrud-editor-save');

/*
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
UTILITY FUNCTIONS
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
*/

function insertCodeInEditor($editor, $text = '', $preText = '', $postText = '') {
    var cm = $editor.codemirror;
    var startPoint = cm.getCursor('start');
    var endPoint = cm.getCursor('end');
    var selection = cm.getSelection();
    if ($text == '') { $text = selection; }
    cm.replaceSelection($preText + $text + $postText);
    startPoint.ch += $preText.length;
    if (startPoint !== endPoint) {
        endPoint.ch += $postText.length;
    }
    cm.setSelection(startPoint, endPoint);
    cm.focus();
}

function loadThemes() {
    for (const $theme in $themes) {
        var $option = document.createElement('option');
        $option.value = $theme;
        $option.innerHTML = $theme;
        $themeSelect.appendChild($option);
    }
}

function loadLayouts($theme) {
    var $options = $layoutSelect.getElementsByTagName('option');
    while($options.length > 0) {
        $layoutSelect.remove(0);
    }

    for (const $layoutIndex in $themes[$theme]) {
        var $layout = $themes[$theme][$layoutIndex];
        var $option = document.createElement('option');
        $option.value = $layout;
        $option.innerHTML = $layout;
        $layoutSelect.appendChild($option);
    }
}

/*
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
STARTUP
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
*/

document.addEventListener('DOMContentLoaded', function () {

    // themes and layouts
    loadThemes();
    loadLayouts($currentTheme);
    $themeSelect.value = $currentTheme;
    $layoutSelect.value = $currentLayout;
    $themeSelect.addEventListener('change', function() {
        loadLayouts($themeSelect.value);
    });

    // editor
    $ohCrudEditor = new SimpleMDE(
        {
            element: $$('#ohcrud-editor-text'),
            autofocus: true,
            autosave: {
                enabled: true,
                uniqueId: $url,
                delay: 1000,
            },
            spellChecker: false,
            toolbar: [
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
                    action: function customFunction($editor) {
                        $fileToUploadMode = 'image';
                        $file.click();
                    },
                    className: 'fa fa-file-image-o upload-image',
                    title: 'Image',
                },
                {
                    name: 'upload file',
                    action: function customFunction(editor) {
                        $fileToUploadMode = 'file';
                        $file.click();
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

    // save
    $editorSave.addEventListener('click', function() {

        var $data = {
            URL: $url,
            TITLE: $$('#ohcrud-page-title').value,
            TEXT: $ohCrudEditor.value(),
            THEME: $themeSelect.value,
            LAYOUT: $layoutSelect.value
        };

        httpRequest('/api/pages/save/',
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
                window.location.href = $url;
            },
            async function($error) {
                const $json = await $error.json();
                $$('.alert').innerHTML = `<p>${$json.errors.join()}</p>`;
                $$('.alert').classList.add('alert-danger');
                $$('.alert').classList.remove('hidden');
            }
        );

    });

    // upload
    $file.addEventListener('change', function() {

        let $fileToUpload = $file.files[0];
        let $formData = new FormData();

        $formData.append("0", $fileToUpload);

        $$(`.upload-${$fileToUploadMode}`).classList = `fa fa-cog fa-spin upload-${$fileToUploadMode}`;

        httpRequest('/api/files/upload/',
            {
                method: 'POST',
                cache: 'no-cache',
                credentials: 'same-origin',
                body: $formData
            },
            async function($response) {
                $$('.alert').classList.add('hidden');
                const $json = await $response.json();

                if ($fileToUploadMode == 'image') {
                    const $alt = $json.data.NAME.replace(`.${$json.data.TYPE}`, '') ;
                    insertCodeInEditor($ohCrudEditor, `![${$alt}](${$json.data.PATH})`);
                    $$(`.upload-${$fileToUploadMode}`).classList = `fa fa-file-image-o upload-${$fileToUploadMode}`;
                } else {
                    insertCodeInEditor($ohCrudEditor, `[${$json.data.NAME}](${$json.data.PATH})`);
                    $$(`.upload-${$fileToUploadMode}`).classList = `fa fa-file upload-${$fileToUploadMode}`;
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

});