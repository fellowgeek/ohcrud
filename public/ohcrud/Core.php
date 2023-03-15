<?php
namespace OhCrud;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Core {

    const OUTPUT_NULL = null;
    const OUTPUT_HTML = 'HTML';
    const OUTPUT_JSON = 'JSON';

    public $data = [];
    public $errors = [];
    public $success = true;
    public $outputType = null;
    public $outputHeaders = [];
    public $outputHeadersSent = false;
    public $outputStatusCode = 200;
    public $runtime;

    public function setOutputType($outputType) {
        $this->outputType = $outputType;
        return $this;
    }

    public function setOutputHeaders($outputHeaders = array()) {
        if (is_array($outputHeaders) == true) {
            $this->outputHeaders = $outputHeaders;
        } else {
            $this->error('outputHeaders, must be an array.');
        }
        return $this;
    }

    public function setOutputStatusCode($outputStatusCode) {
        $this->outputStatusCode = $outputStatusCode;
        return $this;
    }

    public function setSession($key, $value) {
        session_start();
        if (isset($key) == true && isset($value) == true) {
            $_SESSION[$key] = $value;
        }
        session_write_close();
        return $this;
    }

    public function unsetSession($key) {
        session_start();
        if (isset($key) == true) {
            unset($_SESSION[$key]);
        }
        session_write_close();
        return $this;
    }

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

        if (PHP_SAPI == 'cli' && $this->outputType == null && __OHCRUD_DEBUG_MODE__ == true) {
            $this->debug();
        }

        return $this;
    }

    public function headers() {
        if ($this->outputType == null) {
            return $this;
        }
        if (headers_sent() == false && $this->outputHeadersSent == false) {
            $this->outputHeadersSent = true;
            http_response_code($this->outputStatusCode);
            foreach ($this->outputHeaders as $outputHeader) {
                header($outputHeader);
            }
        }
        return $this;
    }

    public function getCache($key, $duration = 3600) {

        if (__OHCRUD_CACHE_ENABLED__ == false) {
            return false;
        }

        $hash = md5($key) . '.cache';

        if (file_exists(__OHCRUD_CACHE_PATH__ . $hash) == false) {
            return false;
        }

        $age = time() - filemtime(__OHCRUD_CACHE_PATH__ . $hash);
        if ($age >= $duration) {
            return false;
        }

        $data = unserialize(file_get_contents(__OHCRUD_CACHE_PATH__ . $hash));
        return $data;
    }

    public function setCache($key, $data) {

        if (__OHCRUD_CACHE_ENABLED__ == false) {
            return false;
        }

        $hash = md5($key) . '.cache';
        $data = serialize($data);
        return file_put_contents(__OHCRUD_CACHE_PATH__ . $hash, $data);
    }

    public function unsetCache($key) {

        $hash = md5($key) . '.cache';
        if ( \file_exists(__OHCRUD_CACHE_PATH__ . $hash) == true) {
            \unlink(__OHCRUD_CACHE_PATH__ . $hash);
            return true;
        }
        return false;
    }

    public function log($level, $message, array $context = array()) {

        if (__OHCRUD_LOG_ENABLED__ == false) {
            return $this;
        }

        $logger = new Logger('OHCRUD');
        $stream = new StreamHandler(__OHCRUD_LOG_FILE__, Logger::DEBUG);
        $stream->setFormatter(new \Monolog\Formatter\LineFormatter("[%datetime%] %channel%.%level_name%:\n%message%\n%context%\n%extra%\n----------------------------------------\n", "Y-m-d H:i:s"));
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

    public function redirect($url) {
        if (headers_sent() == false) {
            header('Location: ' . $url);
            die();
        }
        return false;
    }

    public function console($message = '', $color = 'WHT', $shouldAddNewLine = true) {

        if ( PHP_SAPI != 'cli') {
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

    public function debug($expression = null) {

        \ref::config('expLvl', __OHCRUD_DEBUG_EXPANDED_LEVEL__);
        \ref::config('shortcutFunc', ['debug', 'r', 'rt']);

        if (isset($expression) == true) {
            if (is_object($expression) == true) {
                $clone = clone $expression;
                if (isset($clone->config) == true) {
                    $clone->config = 'Redacted from debug.';
                }
            } else {
                $clone = $expression;
            }
            r($clone);
        } else {
            $clone = clone $this;
            if (isset($clone->config) == true) {
                $clone->config = 'Redacted from debug.';
            }
            r($clone);
        }

        return $this;
    }

}
