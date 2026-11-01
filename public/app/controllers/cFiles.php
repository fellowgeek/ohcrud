<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cFiles - files controller used by the CMS
class cFiles extends \app\models\mFiles {

    // Define permissions for the controller.
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'image' => __OHCRUD_PERMISSION_ALL__,
        'upload' => 1,
    ];

    // Define an array of file extensions allowed for uploading.
    private $filesAllowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'csv', 'txt', 'pdf', 'xml', 'xlsx', 'json', 'zip', 'mp3'];

    // Allowed MIME types
    private $mimeTypesAllowed = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'text/csv',
        'text/plain',
        'application/pdf',
        'application/xml',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/json',
        'application/zip',
        'audio/mpeg',
    ];

    // Define the 'upload' method for this controller.
    public function upload($request) {

        // Set the output type of this controller to JSON.
        $this->outputType = \ohCRUD\Core::OUTPUT_JSON;

        // Performs CSRF token validation and displays an error if the token is missing or invalid.
        if ($this->checkCSRF($request->CSRF ?? '') === false) {
            $this->error('Missing or invalid CSRF token.');
            $this->output();
            return $this;
        }

        // Validation: Check if a file with index 0 is present in the uploaded files.
        if (isset($_FILES[0]) == false) {
            // If not, generate an error message
            $this->error('I\'m sorry Dave, I\'m afraid I can\'t do that.');
            $this->output();
            return $this;
        }

        // Get file information such as name, extension, and generate a unique path.
        $BASENAME = basename($_FILES[0]['name']);
        $NAME = pathinfo($BASENAME, PATHINFO_FILENAME);
        $TYPE = strtolower(pathinfo($BASENAME, PATHINFO_EXTENSION));
        $PATH = 'global/files/' . md5($BASENAME . microtime()) . '.' . $TYPE;
        $TEMP = $_FILES[0]['tmp_name'];

        // Check allowed file extension
        if (in_array($TYPE, $this->filesAllowed) == false) {
            // If not allowed, generate an error message and respond with status code 415 ( Unsupported Media Type )
            $this->error('This file type is not allowed.', 415);
            $this->output();
            return $this;
        }

        // Detect MIME type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $TEMP);

        // Check allowed MIME type, if not allowed, generate an error message and respond with status code 415 ( Unsupported Media Type )
        if (!in_array($detectedMime, $this->mimeTypesAllowed)) {
            $this->error('Unsupported file type detected.', 415);
            $this->output();
            return $this;
        }

        // Move the uploaded file to a designated path.
        if (move_uploaded_file($TEMP, __SELF__ . $PATH) == false) {
            $this->error('Unable to move uploaded file.', 500);
            $this->output();
            return $this;
        }

        // If the file is an image, get its dimensions.
        if (in_array($TYPE, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            $dimensions = $this->getImageDimensions(__SELF__ . $PATH);
            if ($dimensions === false) {
                $WIDTH = null;
                $HEIGHT = null;
            } else {
                $WIDTH = $dimensions['width'];
                $HEIGHT = $dimensions['height'];
            }
        }

        // Prepare parameters for file insertion into the database.
        $filesParameters = [
            'NAME' => $NAME,
            'PATH' => '/' . $PATH,
            'SIZE' => $_FILES[0]['size'] ?? 0,
            'TYPE' => $TYPE,
            'W' => $WIDTH ?? null,
            'H' => $HEIGHT ?? null,
            'MDATE' => date('Y-m-d H:i:s'),
            'IP' => $_SERVER['REMOTE_ADDR'] ?? '',
            'STATUS' => $this::ACTIVE
        ];

        // Create a new file entry in the database using the 'create' method.
        $filesOutput = $this->create('Files', $filesParameters);
        if ($this->success == false) {
            // If the creation fails, generate an error message.
            $this->errors = [];
            $this->error('Another file with the same name already exists.', 409);
            $this->output();
            return $this;
        }

        // Check if the file creation was successful and retrieve the last inserted ID.
        if (isset($filesOutput->lastInsertId) == true) {
            $filesParameters['ID'] = $filesOutput->lastInsertId;
        }

        // Set the controller's data to the file parameters and output it.
        $this->data = $filesParameters;
        $this->output();
    }

    // Validate image work before decoding; stream derivatives without unbounded disk caching.
    public function image($request) {
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);
        if (isset($request->filename) == false || is_string($request->filename) == false ||
            $request->filename === '' || basename($request->filename) !== $request->filename) {
            return $this->error('A valid filename is required.')->output();
        }
        $basePath = realpath(__SELF__ . 'global/files');
        $originalFilePath = realpath(__SELF__ . 'global/files/' . $request->filename);
        if ($basePath === false || $originalFilePath === false || is_file($originalFilePath) == false ||
            strncmp($originalFilePath, $basePath . DIRECTORY_SEPARATOR, strlen($basePath) + 1) !== 0) {
            return $this->error('Image not found.', 404)->output();
        }

        // Reject malformed inputs instead of coercing arrays, fractions or trailing text.
        $dimensions = [];
        foreach (['w' => 3840, 'h' => 2160, 'q' => 100] as $key => $maximum) {
            $dimensions[$key] = null;
            if (isset($request->$key) == true) {
                $value = filter_var($request->$key, FILTER_VALIDATE_INT);
                if ($value === false || $value < 1 || $value > $maximum) {
                    return $this->error('Invalid image dimensions or quality.')->output();
                }
                $dimensions[$key] = $value;
            }
        }
        if (filesize($originalFilePath) > 16 * 1024 * 1024) {
            return $this->error('Image is too large.', 413)->output();
        }
        $imageInfo = @getimagesize($originalFilePath);
        $mime = $imageInfo['mime'] ?? '';
        $decoders = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng',
            'image/gif' => 'imagecreatefromgif', 'image/webp' => 'imagecreatefromwebp'];
        if ($imageInfo === false || isset($decoders[$mime]) == false) {
            return $this->error('Unsupported image format.', 415)->output();
        }
        [$originalWidth, $originalHeight] = $imageInfo;
        if ($originalWidth < 1 || $originalHeight < 1 || $originalWidth * $originalHeight > 20000000) {
            return $this->error('Image has too many pixels.', 413)->output();
        }
        $width = $dimensions['w'];
        $height = $dimensions['h'];
        $quality = $dimensions['q'];
        if ($width === null && $height === null && $quality === null) {
            $this->serveImage($originalFilePath, isset($request->rand) == false);
            return;
        }

        // Bound the computed dimensions as well as the input dimensions.
        $newWidth = $width ?? ($height !== null ? max(1, (int) floor($originalWidth * $height / $originalHeight)) : $originalWidth);
        $newHeight = $height ?? ($width !== null ? max(1, (int) floor($originalHeight * $width / $originalWidth)) : $originalHeight);
        if ($newWidth > 3840 || $newHeight > 2160) {
            return $this->error('Calculated image dimensions exceed the limit.')->output();
        }
        $sourceImage = @$decoders[$mime]($originalFilePath);
        if ($sourceImage === false) {
            return $this->error('Invalid image data.', 415)->output();
        }
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparentColor = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefill($newImage, 0, 0, $transparentColor);
        }
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: ' . (isset($request->rand) == true ? 'no-store' : 'public, max-age=86400'));
        switch ($mime) {
            case 'image/jpeg': imagejpeg($newImage, null, $quality ?? 75); break;
            case 'image/webp': imagewebp($newImage, null, $quality ?? 80); break;
            case 'image/png': imagepng($newImage); break;
            case 'image/gif': imagegif($newImage); break;
        }
    }

    /**
     * Helper to send an image as the response with correct headers.
     *
     * @param string $filePath Path to the image file.
     */
    private function serveImage($filePath, $cache = true) {

        $imageInfo = getimagesize($filePath);
        $mime = $imageInfo['mime'];

        header("Content-Type: {$mime}");
        header('X-Content-Type-Options: nosniff');
        header("Content-Length: " . filesize($filePath));
        if ($cache === false) {
            header("Cache-Control: no-cache, no-store, must-revalidate");
            header("Pragma: no-cache");
            header("Expires: 0");
        } else {
            header_remove("Pragma");
            header("Cache-Control: public, max-age=2592000"); // Cache for 30 days
            header("Expires: " . gmdate("D, d M Y H:i:s", time() + 2592000) . " GMT");
            header("ETag: \"" . md5($filePath . filesize($filePath)) . "\"");
        }
        // Remove X-Powered-By header for security reasons
        if (ini_get('expose_php') == true) {
            ini_set('expose_php', 'Off');
            header_remove("X-Powered-By");
        }
        readfile($filePath);
        die();
    }

    /**
     * Returns the dimensions of an image file.
     *
     * @param string $filePath Path to the image file.
     * @return array|false An associative array with 'width' and 'height' keys, or false if the file does not exist or is not an image.
     */
    private function getImageDimensions($filePath) {
        if (file_exists($filePath) == false) {
            return false;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $dimensions = ['width' => null, 'height' => null];

        // Special handling for SVG
        if ($ext === 'svg') {
            $svgContent = file_get_contents($filePath);

            $widthPattern = '/<svg[^>]+?width=["\']([0-9.]+)(?:px|em|pt|%)?["\']/i';
            $heightPattern = '/<svg[^>]+?height=["\']([0-9.]+)(?:px|em|pt|%)?["\']/i';

            // First, try to get dimensions from the 'width' and 'height' attributes.
            if (preg_match($widthPattern, $svgContent, $matches)) {
                $dimensions['width'] = (int) $matches[1];
            }
            if (preg_match($heightPattern, $svgContent, $matches)) {
                $dimensions['height'] = (int) $matches[1];
            }

            // If width and height are not found, try to get them from the 'viewBox'.
            if (is_null($dimensions['width']) && is_null($dimensions['height'])) {
                // The viewBox attribute is typically 'min-x min-y width height'.
                $viewBoxPattern = '/<svg[^>]+?viewBox=["\']\s*([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s*["\']/i';

                if (preg_match($viewBoxPattern, $svgContent, $matches)) {
                    // The third and fourth captured values are the width and height.
                    $dimensions['width'] = (int) $matches[3];
                    $dimensions['height'] = (int) $matches[4];
                }
            }

            return $dimensions;
        }

        // For raster images
        $size = @getimagesize($filePath);
        if ($size === false) {
            return false;
        }

        $dimensions['width'] = $size[0];
        $dimensions['height'] = $size[1];

        return $dimensions;
    }

}
