<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cFiles - files controller used by the CMS
class cFiles extends \app\models\mFiles {

    // Define permissions for the controller.
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'upload' => 1
    ];

    // Define an array of file extensions allowed for uploading.
    private $filesAllowed = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'csv', 'txt', 'pdf', 'xml', 'xlsx', 'json', 'zip'];

    // Allowed MIME types
    private $mimeTypesAllowed = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/svg+xml',
        'text/csv',
        'text/plain',
        'application/pdf',
        'application/xml',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/json',
        'application/zip'
    ];

    // Define the 'upload' method for this controller.
    public function upload($request) {

        // Set the output type of this controller to JSON.
        $this->outputType = \ohCRUD\Core::OUTPUT_JSON;

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->payload->CSRF ?? '') == false) {
            $this->error('Missing or invalid CSRF token.');
            $this->output();
            return $this;
        }

        // Validation: Check if a file with index 0 is present in the uploaded files.
        if (isset($_FILES[0]) == false) {
            // If not, generate an error message and respond with a 403 Forbidden status.
            $this->error('I\'m sorry Dave, I\'m afraid I can\'t do that.', 403);
            $this->output();
            return $this;
        }

        // Get file information such as name, extension, and generate a unique path.
        $NAME = basename($_FILES[0]['name']);
        $TYPE = strtolower(pathinfo($NAME, PATHINFO_EXTENSION));
        $PATH = 'global/files/' . md5($NAME . microtime()) . '.' . $TYPE;
        $TEMP  = $_FILES[0]['tmp_name'];

        // Check allowed file extension
        if (in_array($TYPE, $this->filesAllowed) == false) {
            // If not allowed, generate an error message and respond with a 403 Forbidden status.
            $this->error('This file type is not allowed.', 403);
            $this->output();
            return $this;
        }

        // Detect MIME type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $TEMP);
        finfo_close($finfo);

        // Check allowed MIME type
        if (!in_array($detectedMime, $this->mimeTypesAllowed)) {
            $this->error('Unsupported file type detected.', 403);
            $this->output();
            return $this;
        }

        // Move the uploaded file to a designated path.
        if (move_uploaded_file($TEMP, __SELF__ . $PATH) == false) {
            $this->error('Unable to move uploaded file.', 403);
            $this->output();
            return $this;
        }

        // Prepare parameters for file insertion into the database.
        $filesParameters = [
            'NAME'      => $NAME,
            'PATH'      => '/' . $PATH,
            'SIZE'      => $_FILES[0]['size'] ?? 0,
            'TYPE'      => $TYPE,
            'IP'        => $_SERVER['REMOTE_ADDR'] ?? '',
            'STATUS'    => $this::ACTIVE
        ];

        // Create a new file entry in the database using the 'create' method.
        $filesOutput = $this->create('Files', $filesParameters);

        // Check if the file creation was successful and retrieve the last inserted ID.
        if (isset($filesOutput->lastInsertId) == true) {
            $filesParameters['ID'] = $filesOutput->lastInsertId;
        }

        // Set the controller's data to the file parameters and output it.
        $this->data = $filesParameters;
        $this->output();
    }

}
