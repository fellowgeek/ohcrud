<?php
namespace ohCRUD;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use stdClass;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Class Core - core operations class for ohCRUD, all other ohCRUD classed inherit from this class
class Core {

    const ACTIVE = 1;
    const INACTIVE = 0;

    const OUTPUT_NULL = null;
    const OUTPUT_HTML = 'HTML';
    const OUTPUT_JSON = 'JSON';

    public $data;
    public $errors = [];
    public $success = true;
    public $outputType = null;
    public $outputHeaders = [];
    public $outputHeadersSent = false;
    public $outputStatusCode = 200;
    public $runtime;
    public $version = '2.5';

    // Set the output type for the response.
    public function setOutputType($outputType) {
        $this->outputType = $outputType;
        return $this;
    }

    // Set custom output headers for the response.
    public function setOutputHeaders($outputHeaders = array()) {
        if (is_array($outputHeaders) == true) {
            $this->outputHeaders = $outputHeaders;
        } else {
            $this->error('outputHeaders, must be an array.');
        }
        return $this;
    }

    // Set the HTTP status code for the response.
    public function setOutputStatusCode($outputStatusCode) {
        $this->outputStatusCode = $outputStatusCode;
        return $this;
    }

    // Set a session variable and ensure it is stored.
    public function setSession($key, $value) {
        session_start();
        if (isset($key) == true && isset($value) == true) {
            $_SESSION[$key] = $value;
        }
        session_write_close();
        return $this;
    }

    // Unset a session variable.
    public function unsetSession($key) {
        session_start();
        if (isset($key) == true) {
            unset($_SESSION[$key]);
        }
        session_write_close();
        return $this;
    }

    // Generate a CSRF token and store it in the session.
    public function CSRF() {

        if (empty($_SESSION['CSRF']) == true) {
            $this->setSession('CSRF', bin2hex(random_bytes(32)));
        }
        return $_SESSION['CSRF'];
    }

    // Check if a given token matches the stored CSRF token.
    public function checkCSRF($token) {
        // Disable CSRF check for debug mode
        if (__OHCRUD_DEBUG_MODE__ == true) return true;
        return hash_equals($_SESSION['CSRF'] ?? '', $token);
    }

    // Generate and send the response based on the output type.
    public function output() {

        $output = '';

        switch ($this->outputType) {
            case 'HTML':
                if (is_string($this->data) == true) {
                    $output = $this->data;
                }
                break;
            case 'JSON':
                if (in_array('Content-Type: application/json', $this->outputHeaders) == false) {
                    array_push($this->outputHeaders, 'Content-Type: application/json');
                }
                if (headers_sent() == false && $this->outputHeadersSent == false) {
                    $json = clone $this;
                    unset($json->config);
                    unset($json->outputType);
                    unset($json->outputHeaders);
                    unset($json->outputHeadersSent);
                    unset($json->permissions);
                    unset($json->db);
                    if (__OHCRUD_DEBUG_MODE__ == true) {
                        $json->runtime = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
                    }
                    $output = json_encode($json, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
                }
                break;
        }

        if (__OHCRUD_DEBUG_MODE__ == true) {
            $this->runtime = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
        }

        $this->headers();
        if (empty($output) == false) {
            print($output);
        }

        if (PHP_SAPI === 'cli' && $this->outputType == null && __OHCRUD_DEBUG_MODE__ == true) {
            $this->debug();
        }

        return $this;
    }

    // Send HTTP headers for the response.
    public function headers() {
        if ($this->outputType == null) {
            return $this;
        }
        if (headers_sent() == false && $this->outputHeadersSent == false) {
            $this->outputHeadersSent = true;
            http_response_code($this->outputStatusCode);
            // Disable broswer side caching
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            // Set X-Frame-Options to prevent clickjacking
            header('X-Frame-Options: ' . __X_FRAME_OPTIONS__ ?? 'SAMEORIGIN');
            // Set X-Content-Type-Options to prevent MIME type sniffing
            header('X-Content-Type-Options: ' . __X_CONTENT_TYPE_OPTIONS__ ?? 'nosniff');
            foreach ($this->outputHeaders as $outputHeader) {
                header($outputHeader);
            }
            // Remove X-Powered-By header for security reasons
            if (ini_get('expose_php') == true) {
                ini_set('expose_php', 'Off');
                header_remove("X-Powered-By");
            }
        }
        return $this;
    }

    // Perform an HTTP redirect.
    public function redirect($url) {
        if (headers_sent() == false) {
            header('Location: ' . $url);
            die();
        }
        return false;
    }

    // Retrieve data from cache if available and not expired.
    public function getCache($key, $duration = 3600) {

        if (__OHCRUD_CACHE_ENABLED__ == false) {
            return false;
        }

        $hash = md5($key) . '.cache';
        $path = __OHCRUD_CACHE_PATH__ . $hash;

        if (file_exists($path) == false) {
            return false;
        }

        $age = time() - filemtime($path);
        if ($age >= $duration) {
            return false;
        }

        $data = @unserialize(file_get_contents($path), ['allowed_classes' => false]);
        return $data;
    }

    // Store data in cache.
    public function setCache($key, $data) {

        if (__OHCRUD_CACHE_ENABLED__ == false) {
            return false;
        }

        $hash = md5($key) . '.cache';
        $path = __OHCRUD_CACHE_PATH__ . $hash;

        $serialized = serialize($data);
        return file_put_contents($path, $serialized, LOCK_EX);
    }

    // Remove data from cache.
    public function unsetCache($key) {

        $hash = md5($key) . '.cache';
        $path = __OHCRUD_CACHE_PATH__ . $hash;

        if (file_exists($path) == true) {
            unlink($path);
            return true;
        }
        return false;
    }

    // Function to encrypt text
    public function encryptText($text, $password = '') {
        $method = 'aes-256-cbc';
        $key = hash('sha256', $password . __OHCRUD_SECRET__, true);
        $iv = openssl_random_pseudo_bytes(16);

        $encrypted = openssl_encrypt($text, $method, $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    // Function to decrypt text
    public function decryptText($encryptedText, $password = '') {
        $method = 'aes-256-cbc';
        $key = hash('sha256', $password . __OHCRUD_SECRET__, true);
        $data = base64_decode($encryptedText);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        return openssl_decrypt($encrypted, $method, $key, 0, $iv);
    }

    // Log messages using Monolog if logging is enabled.
    public function log($level, $message, array $context = array(), $channel = 'system', $logFile = 'app.log') {

        if (__OHCRUD_LOG_ENABLED__ == false) {
            return $this;
        }

        $logger = new Logger($channel);
        $stream = new StreamHandler(__OHCRUD_LOG_PATH__ . $logFile, Logger::DEBUG);
        $stream->setFormatter(new \Monolog\Formatter\JsonFormatter());
        $logger->pushHandler($stream);

        try {
            $logger->log($level ?? 'debug', $message, $context);
        } catch(\Exception $e) {
            $this->outputStatusCode = 500;
            $this->success = false;
            $this->errors[] = $e->getMessage();
            if (__OHCRUD_DEBUG_MODE__ == true) {
                $this->debug();
            }
        }
        return $this;
    }

    // Handle errors by logging and setting the HTTP status code.
    public function error($message, $outputStatusCode = 500) {
        $debug = [];

        $this->outputStatusCode = $outputStatusCode;
        $this->success = false;
        $this->errors[] = $message;

        if (__OHCRUD_DEBUG_MODE__ == true) {
            $debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }

        if ($outputStatusCode != 404) {
            $this->log('error', $message, $debug);
        }

        return $this;
    }

    // Register core error handlers
    public function registerCoreErrorHandlers() {
        if (__OHCRUD_DEBUG_MODE__ == false && isset($GLOBALS['coreErrorHandlersRegistered']) == false) {
            set_error_handler([$this, 'coreErrorHandler']);
            set_exception_handler([$this, 'coreExceptionHandler']);
            register_shutdown_function([$this, 'coreShutdownHandler']);
            $GLOBALS['coreErrorHandlersRegistered'] = true;
        }
    }

    // Custom error handler to convert errors to exceptions
    public function coreErrorHandler($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    // Custom exception handler for uncaught exceptions
    public function coreExceptionHandler($exception) {
        $this->log('error', $exception->getMessage(), debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
    }

    // Shutdown function to handle fatal errors
    public function coreShutdownHandler() {
        $error = error_get_last();
        if ($error && ($error['type'] === E_ERROR || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR || $error['type'] === E_PARSE)) {
            $this->log('error', $error['message'], $error);
        }
    }

    // Run a CLI route in the background
    public function background($command, $wait = 0) {
        if ($wait > 0) {
            pclose(
                popen('sleep ' . $wait . ' && php ' . __SELF__ . 'index.php ' . $command . ' > /dev/null 2>&1 &', 'r')
            );
        } else {
            exec('php ' . __SELF__ . 'index.php ' . $command . ' > /dev/null 2>&1 &');
        }
    }

    // Output messages in the console (for CLI environment).
    public function console($message = '', $color = 'WHT', $shouldAddNewLine = true) {

        if ( PHP_SAPI !== 'cli') {
            return false;
        }

        $colors = [
            "BLK" =>    "\033[30m",
            "RED" =>    "\033[31m",
            "GRN" =>    "\033[32m",
            "YEL" =>    "\033[33m",
            "BLU" =>    "\033[34m",
            "PUR" =>    "\033[35m",
            "CYN" =>    "\033[36m",
            "WHT" =>    "\033[37m",
            "RED+" =>   "\033[1;31m",
            "GRN+" =>   "\033[1;32m",
            "YEL+" =>   "\033[1;33m",
            "BLU+" =>   "\033[1;34m",
            "PUR+" =>   "\033[1;35m",
            "CYN+" =>   "\033[1;36m",
            "WHT+" =>   "\033[1;37m",
            "RST" =>    "\033[0m"
        ];

        print(($colors[$color] ?? $colors['WHT']) . $message . $colors['RST'] . ($shouldAddNewLine ? "\n" : ''));
    }

    // Debug information by inspecting variables or the class itself.
    public function debug($expression = null, $label = null, $showLineNumber = true) {

        \ref::config('expLvl', __OHCRUD_DEBUG_EXPANDED_LEVEL__);
        \ref::config('shortcutFunc', ['debug', 'r', 'rt']);

        // Set debug panel label (if any)
        $GLOBALS['debugLabel'] = $label;
        // Shoud debug panel hide the line number?
        $GLOBALS['debugShowLineNo'] = $showLineNumber;

        if (isset($expression) == false) {
            $expression = $this;
        }

        if (is_object($expression) == true) {
            $clone = clone $expression;
            if (property_exists($clone, 'config') == true) {
                $clone->config = 'Redacted from debug.';
            }
        } else {
            $clone = $expression;
        }
        r($clone);

        return $this;
    }

}
