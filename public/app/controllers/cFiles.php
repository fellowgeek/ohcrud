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

    // Resizes, caches, and serves an image based on GET parameters.
    public function image($request) {

        // Validation & File Setup ---
        if (empty($request->filename)) {
            $this->error('Filename is required.');
        }

        $basePath = __SELF__ . 'global/files/';
        $originalFilePath = $basePath . basename($request->filename); // Use basename for security

        if (!file_exists($originalFilePath)) {
            $this->error('Image not found.', 404);
        }

        // Check if cache is enabled
        $cacheEnabled = true;
        if (isset($request->rand) == true) {
            $cacheEnabled = false;
        } else {
            $cacheEnabled = true;
        }

        // Validate optional parameters
        $width = isset($request->w) ? (int) $request->w : null;
        $height = isset($request->h) ? (int) $request->h : null;
        $quality = isset($request->q) ? (int) $request->q : null;

        if ($width !== null && ($width <= 0 || $width > 3840)) {
            $this->error('Width must be a positive integer up to 3840.', 400);
        }
        if ($height !== null && ($height <= 0 || $height > 2160)) {
            $this->error('Height must be a positive integer up to 2160.', 400);
        }
        if ($quality !== null && ($quality < 1 || $quality > 100)) {
            $this->error('Quality must be an integer between 1 and 100.', 400);
        }

        // No transformations needed
        if ($width === null && $height === null && $quality === null) {
            $this->serveImage($originalFilePath);
            return;
        }

        // Generate New Filename & Check Cache
        $path_info = pathinfo($originalFilePath);
        $original_filename = $path_info['filename'];
        $extension = $path_info['extension'];

        $suffix = '';
        if ($width) $suffix .= "_w{$width}";
        if ($height) $suffix .= "_h{$height}";
        if ($quality) $suffix .= "_q{$quality}";

        $newFilename = "{$original_filename}{$suffix}.{$extension}";
        $newFilePath = $basePath . $newFilename;

        if (file_exists($newFilePath) && $cacheEnabled == true) {
            $this->serveImage($newFilePath);
            return;
        }

        // Image Processing
        $imageInfo = @getimagesize($originalFilePath);
        $mime = $imageInfo['mime'] ?? '';

        $sourceImage = null;
        switch ($mime) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($originalFilePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($originalFilePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($originalFilePath);
                break;
             case 'image/webp':
                $sourceImage = imagecreatefromwebp($originalFilePath);
                break;
            default:
                $this->setOutputType(\ohCRUD\Core::OUTPUT_HTML);
                $this->error('Unsupported image format.', 415);
                $this->output();
                return;
        }

        if (!$sourceImage) {
            $this->error('Failed to process image.', 500);
        }

        list($originalWidth, $originalHeight) = [$imageInfo[0], $imageInfo[1]];

        // Calculate new dimensions
        if ($width && !$height) { // Width only, maintain aspect ratio
            $newWidth = $width;
            $newHeight = floor($originalHeight * ($width / $originalWidth));
        } elseif (!$width && $height) { // Height only, maintain aspect ratio
            $newHeight = $height;
            $newWidth = floor($originalWidth * ($height / $originalHeight));
        } elseif ($width && $height) { // Exact dimensions
            $newWidth = $width;
            $newHeight = $height;
        } else { // No dimensions, but quality might be set
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and WebP formats
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparentColor = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefill($newImage, 0, 0, $transparentColor);
        }

        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // Save the new image
        switch ($mime) {
            case 'image/jpeg':
                // Default quality 75 if not set
                imagejpeg($newImage, $newFilePath, $quality ?? 75);
                break;
            case 'image/webp':
                // Default quality 80 if not set
                imagewebp($newImage, $newFilePath, $quality ?? 80);
                break;
            case 'image/png':
                imagepng($newImage, $newFilePath);
                break;
            case 'image/gif':
                imagegif($newImage, $newFilePath);
                break;
        }

        // Output New Image
        $this->serveImage($newFilePath, $cacheEnabled);
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
        header("Content-Length: " . filesize($filePath));
        if ($cache === false) {
            header("Cache-Control: no-cache, no-store, must-revalidate");
            header("Pragma: no-cache");
            header("Expires: 0");
        } else {
            header("Cache-Control: public, max-age=2592000"); // Cache for 30 days
            header("Expires: " . gmdate("D, d M Y H:i:s", time() + 2592000) . " GMT");
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
