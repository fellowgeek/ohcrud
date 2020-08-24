<?php
namespace app\controllers;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Files extends \app\models\Files {

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'upload' => 1
    ];

    private $filesAllowed = ['jpg','png','csv','txt','pdf','xml','json','zip'];

    public function upload($request) {

        $this->outputType = 'JSON';

        // validation
        if (isset($_FILES[0]) == false) {
            $this->error('I\'m sorry Dave, I\'m afraid I can\'t do that.', 403);
            $this->output();
            return $this;
        }

        $NAME = basename($_FILES[0]['name']);
        $TYPE = strtolower(pathinfo($NAME, PATHINFO_EXTENSION));
        $PATH = 'global-assets/files/' . md5($NAME . microtime()) . '.' . $TYPE;

        // validation
        if (in_array($TYPE, $this->filesAllowed) == false) {
            $this->error('I\'m sorry Dave, I\'m afraid I can\'t do that.', 403);
            $this->output();
            return $this;
        }

        if (move_uploaded_file($_FILES[0]['tmp_name'], __SELF__ . $PATH) == false) {
            $this->error('Unable to move uploaded file.', 403);
            $this->output();
            return $this;
        }

        $filesParameters = [
            'NAME'      => $NAME,
            'PATH'      => '/' . $PATH,
            'SIZE'      => $_FILES[0]['size'] ?? 0,
            'TYPE'      => $TYPE,
            'IP'        => $_SERVER['REMOTE_ADDR'] ?? '',
            'STATUS'    => 1
        ];

        $files = new \app\models\Files;
        $filesOutput = $files->create('Files', $filesParameters);
        if (isset($filesOutput->data->lastInsertId) == true) {
            $filesParameters['ID'] = $filesOutput->data->lastInsertId;
        }

        $this->data = $filesParameters;
        $this->output();
    }

}
