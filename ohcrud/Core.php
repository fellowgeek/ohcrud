<?php
namespace OhCrud;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Core {

    public $data = [];
    public $errors = [];
    public $success = true;
    public $outputType = null;
    public $outputHeaders = [];
    public $outputHeadersSent = false;
    public $outputStatusCode = 200;

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
                if (in_array('Content-Type: application/javascript', $this->outputHeaders) == false) {
                    array_push($this->outputHeaders, 'Content-Type: application/javascript');
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
            foreach($this->outputHeaders as $outputHeader) {
                header($outputHeader);
            }
        }
        return $this;
    }

    public function log($level = 'debug', $message, array $context = array()) {

        $logger = new Logger('OHCRUD');
        $stream = new StreamHandler(__OHCRUD_LOG_FILE__, Logger::DEBUG);
        $stream->setFormatter(new \Monolog\Formatter\LineFormatter("[%datetime%] %channel%.%level_name%:\n%message%\n%context%\n%extra%\n----------------------------------------\n", "Y-m-d H:i:s"));
        $logger->pushHandler($stream);

        try {
            $logger->log($level, $message, $context);
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

    public function request($url, $method = 'GET', $data = '', array $headers = array()) {

        $response = '';
        if (is_array($data) == true) {
            $data_string = '';
            foreach($data as $key => $value) { $data_string .= $key . '=' . urlencode($value) . '&'; }
            rtrim($data_string, '&');
            if ($method == 'GET') { $url .= '?' . $data_string; }
        }

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (isset($data_string) == true) ? $data_string : $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            $this->responseInfo = curl_getinfo($ch);
            curl_close ($ch);

            if (isset($this->responseInfo['http_code']) == true) {
                $this->outputStatusCode = $this->responseInfo['http_code'];

                if (in_array($this->responseInfo['http_code'], [200, 201, 202, 203, 204, 205, 206, 207, 208, 226]) == true) {
                    $this->data = json_decode($response);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->data = $response;
                    }
                } else {
                    $this->success = false;
                    $this->errors = json_decode($response);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->error($response);
                    }
                }
                return $this->output();
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return $this->output();
        }
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
