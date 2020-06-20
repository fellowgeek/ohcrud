/*
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
GLOBAL VARIABLES
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
*/

// url
var $url = document.querySelector('#OhCRUD_Page_URL').value;

// file
var $file = document.querySelector('#OhCRUD_File');
var $fileToUploadMode = '';

// editor
var $ohCrudEditor = {};

// save
var $OhCRUD_Page_Button_Save = document.querySelector('#OhCRUD_Page_Button_Save');

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

/*
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
STARTUP
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
*/

document.addEventListener('DOMContentLoaded', function () {

    // editor
    $ohCrudEditor = new SimpleMDE(
        {
            element: document.getElementById('OhCRUD_Page_Text'),
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
    if ($OhCRUD_Page_Button_Save != null) {
        $OhCRUD_Page_Button_Save.addEventListener('click', function() {

            var $data = {
                URL: $url,
                TITLE: $('#OhCRUD_Page_Title').value,
                TEXT: $ohCrudEditor.value(),
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
                    $('.alert').classList.add('hidden');
                    window.location.href = $url;
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

    // upload
    $file.addEventListener('change', function() {

        let $fileToUpload = $file.files[0];
        let $formData = new FormData();

        $formData.append("0", $fileToUpload);

        $(`.upload-${$fileToUploadMode}`).classList = `fa fa-cog fa-spin upload-${$fileToUploadMode}`;

        httpRequest('/api/files/upload/',
            {
                method: 'POST',
                cache: 'no-cache',
                credentials: 'same-origin',
                body: $formData
            },
            async function($response) {
                $('.alert').classList.add('hidden');
                const $json = await $response.json();

                if ($fileToUploadMode == 'image') {
                    const $alt = $json.data.NAME.replace(`.${$json.data.TYPE}`, '') ;
                    insertCodeInEditor($ohCrudEditor, `![${$alt}](${$json.data.PATH})`);
                    $(`.upload-${$fileToUploadMode}`).classList = `fa fa-file-image-o upload-${$fileToUploadMode}`;
                } else {
                    insertCodeInEditor($ohCrudEditor, `[${$json.data.NAME}](${$json.data.PATH})`);
                    $(`.upload-${$fileToUploadMode}`).classList = `fa fa-file upload-${$fileToUploadMode}`;
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

});