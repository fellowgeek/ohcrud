<?php
namespace OhCrud;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Router extends \OhCrud\Core {

    private $request;
    private $count = 0;
    public function __construct($rawPath = null) {

        // global variables
        $GLOBALS['PATH'] = rtrim(parse_url($rawPath, PHP_URL_PATH) ?? '', '/') . '/';
        $GLOBALS['PATH_ARRAY'] = preg_split('[/]', $GLOBALS['PATH'], 0, PREG_SPLIT_NO_EMPTY);

        $this->route($rawPath);
    }

    public function route($rawPath = null) {

        // variables
        $path = $GLOBALS['PATH'];
        $pathArray = $GLOBALS['PATH_ARRAY'];
        $method = '';

        // process cli parameters
        if (PHP_SAPI == 'cli') {
            $parameters = [];
            parse_str(parse_url($rawPath, PHP_URL_QUERY), $parameters);
            $this->request = (object) $parameters;
        } else {
            // process api parameters
            $this->request = (object) \array_merge($_REQUEST, $_GET, $_POST);
            $payload = file_get_contents('php://input');
            if (empty($payload) == false) {
                if ($_SERVER['CONTENT_TYPE'] == 'application/json') $this->request->payload = \json_decode($payload); else $this->request->payload = $payload;
            }
        }

        // try to create the object first
        if (array_key_exists($path, __OHCRUD_ENDPOINTS__) == true) {
            $objectName = __OHCRUD_ENDPOINTS__[$path];
            $object = new $objectName;

            if (isset($object->permissions) == true && $this->checkPermissions($object->permissions) == false) {
                if (isset($_SESSION['User']) == false) $this->authorize(); else $this->forbidden();
            }

            return $this;
        }

        // if object is not found try to create the object from the base path and call object method from ending path
        $method = array_pop($pathArray);
        $path = '/' . implode('/', $pathArray) . '/';

        if (array_key_exists($path, __OHCRUD_ENDPOINTS__) == true) {

            // handle CORS
            if ($this->handleCORS() == false) {
                $this->forbidden();
                return $this;
            }

            $objectName = __OHCRUD_ENDPOINTS__[$path];
            $object = new $objectName;

            if (isset($object->permissions) == true && method_exists($object, $method) == true && $this->checkPermissions($object->permissions, $method) == true) {
                $object->$method($this->request);
            } else {
                if (isset($_SESSION['User']) == false) $this->authorize(); else $this->forbidden();
            }
            return $this;
        }

        // redirect to default path handler, if handler is present or 404
        if (__OHCRUD_DEFAULT_PATH_HANDLER__ != '') {
            $objectName = __OHCRUD_DEFAULT_PATH_HANDLER__;
            $object = new $objectName;
            if (method_exists($object, 'defaultPathHandler') == true) {
                $object->defaultPathHandler($GLOBALS['PATH'], $GLOBALS['PATH_ARRAY']);
                return $this;
            }
        }

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);
        $this->error('Oh CRUD! You just got 404\'d.', 404);
        $this->output();

    }

    private function checkPermissions($expression, $method = null) {

        // grant permission if script is called from command line interface
        if (PHP_SAPI == 'cli') return true;

        // variables
        $objectHasPermission = false;
        $methodHasPermission = false;
        $userPermissions = (isset($_SESSION['User']->PERMISSIONS) == true) ? $_SESSION['User']->PERMISSIONS : false;

        // check object permission
        if (isset($expression['object']) == true) {
            if ($expression['object'] == __OHCRUD_PERMISSION_ALL__ || ($expression['object'] >= $userPermissions && $userPermissions != false))
                $objectHasPermission = true;
        }

        // check method permission
        if (isset($method) == true) {
            if (isset($expression[$method]) == true && ($expression[$method] == __OHCRUD_PERMISSION_ALL__ || ($expression[$method] >= $userPermissions && $userPermissions != false)))
                $methodHasPermission = true;
        } else {
            $methodHasPermission = true;
        }

        return ($objectHasPermission && $methodHasPermission);
    }

    private function authorize() {

        // variables
        $users = new \OhCrud\Users;
        $httpHeaders = getallheaders();
        $userHasLoggedIn = false;

        // authenticate user
        if (isset($_SERVER['PHP_AUTH_USER']) == true || isset($_SERVER['PHP_AUTH_PW']) == true || isset($httpHeaders['Token']) == true) {
            $userHasLoggedIn = $users->login($_SERVER['PHP_AUTH_USER'] ?? null, $_SERVER['PHP_AUTH_PW'] ?? null, $httpHeaders['Token'] ?? null);
        }

        if ($userHasLoggedIn == true) {
            $this->route();
        } else {
            if (isset($httpHeaders['Token']) == true) {
                $this->forbidden();
            } else {
                if(headers_sent() == false) {
                    // unauthorized access
                    header('WWW-Authenticate: Basic realm="' . __SITE__ . '"');
                    header('HTTP/1.0 401 Unauthorized');
                }
                die();
            }
        }
    }

    private function handleCORS() {

        // setup CSRF token
        $this->CSRF();

        // skip CORS chekc if we are in CLI mode
        if (PHP_SAPI == 'cli') return true;

        // check if remote IP filtering is enabled and handle allowed IPs
        if (__OHCRUD_ALLOWED_IPS_ENABLED__ == true) {
            if (in_array($_SERVER['REMOTE_ADDR'], __OHCRUD_ALLOWED_IPS__) == false) return false;
        }
        
        // handle same origin requests user HTTP_ORIGIN and HTTP_REFERER to determine origin of the request
        $origin = strtolower($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin == '') $origin = strtolower(($_SERVER['HTTP_REFERER']) ?? '');
        
        // if origin is not set or request is coming from the current site return true and skip CORS headers
        if ($origin == '') return true;
        if ($origin == ($_SERVER['REQUEST_SCHEME'] ?? '') . '://' . __SITE__) return true;
        
        // handle cross origin requests
        if (in_array($origin, __OHCRUD_ALLOWED_ORIGINS__) == true) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
            if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
                header("Access-Control-Allow-Headers: token, Content-Type, Accept, Origin");
                die();
            }
            return true;
        } else {
            return false;
        }

    }

    private function forbidden() {

        $this->setOutputType(\OhCrud\Core::OUTPUT_JSON);
        $this->error('I\'m sorry Dave, I\'m afraid I can\'t do that...', 403);
        $this->output();
    }

}
