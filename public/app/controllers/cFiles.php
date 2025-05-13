<?php
namespace app\controllers;

// @TODO add mime type filtering

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

    // Define the 'upload' method for this controller.
    public function upload($request) {

        // Set the output type of this controller to JSON.
        $this->outputType = \OhCrud\Core::OUTPUT_JSON;

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
        $PATH = 'global-assets/files/' . md5($NAME . microtime()) . '.' . $TYPE;

        // Validation: Check if the file type (extension) is allowed.
        if (in_array($TYPE, $this->filesAllowed) == false) {
            // If not allowed, generate an error message and respond with a 403 Forbidden status.
            $this->error('I\'m sorry Dave, I\'m afraid I can\'t do that.', 403);
            $this->output();
            return $this;
        }

        // Move the uploaded file to a designated path.
        if (move_uploaded_file($_FILES[0]['tmp_name'], __SELF__ . $PATH) == false) {
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

        // Create an instance of the mFiles model.
        $files = new \app\models\mFiles;
        // Create a new file entry in the database using the 'create' method.
        $filesOutput = $files->create('Files', $filesParameters);

        // Check if the file creation was successful and retrieve the last inserted ID.
        if (isset($filesOutput->lastInsertId) == true) {
            $filesParameters['ID'] = $filesOutput->lastInsertId;
        }

        // Set the controller's data to the file parameters and output it.
        $this->data = $filesParameters;
        $this->output();
    }

}
