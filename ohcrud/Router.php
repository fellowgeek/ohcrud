<?php
namespace OhCrud;

// prevent direct access
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

class Router extends \OhCrud\Core {

    public function __construct($rawPath = null) {

        // global variables
        $GLOBALS['PATH'] = rtrim(parse_url($rawPath, PHP_URL_PATH), '/') . '/';
        $GLOBALS['PATH_ARRAY'] = preg_split('[/]', $GLOBALS['PATH'], NULL, PREG_SPLIT_NO_EMPTY);

        // variables
        $path = $GLOBALS['PATH'];
        $pathArray = $GLOBALS['PATH_ARRAY'];
        $method = '';
        $ohcrudEndPoints = unserialize(__OHCRUD_ENDPOINTS__);

        // try to create the object first
        if (array_key_exists($path, $ohcrudEndPoints) == true) {
            $objectName = $ohcrudEndPoints[$path];
            $object = new $objectName;

            if (isset($object->permissions) == true && $this->checkPermissions($object->permissions) == false) {
                if (isset($_SESSION['User']) == false) $this->authorize(); else $this->forbidden();
            }

            return $this;
        }

        // if object is not found try to create the object from the base path and call object method from ending path
        $method = array_pop($pathArray);
        $path = '/' . implode('/', $pathArray) . '/';

        if (array_key_exists($path, $ohcrudEndPoints) == true) {
            $objectName = $ohcrudEndPoints[$path];
            $object = new $objectName;

            if (isset($object->permissions) == true && method_exists($object, $method) == true && $this->checkPermissions($object->permissions, $method) == true) {
                $request = (object) \array_merge($_REQUEST, $_GET, $_POST);
                if (empty($_GET) == false)  $request->GET = (object) $_GET;
                if (empty($_POST) == false)  $request->POST = (object) $_POST;

                $payload = file_get_contents('php://input');
                if (empty($payload) == false)
                    if ($_SERVER['CONTENT_TYPE'] == 'application/json') $request->payload = \json_decode($payload); else $request->payload = $payload;
                $object->$method($request);
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

        $this->outputType = 'JSON';
        $this->error('Oh CRUD! You just got 404\'d.', 404);
        $this->output();

    }

    private function checkPermissions($expression, $method = null) {

        // grand permission if script is called from command line interface
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

        if (isset($_SESSION['User']) == false) {
            if (isset($_SERVER['PHP_AUTH_USER']) == true && isset($_SERVER['PHP_AUTH_PW']) == true) {
                // authenticate user
                $user = new \OhCrud\Users;
                $userHasLoggedIn = $user->login($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

                if ($userHasLoggedIn == false) {
                    header('WWW-Authenticate: Basic realm="' . __SITE__ . '"');
                    header('HTTP/1.0 401 Unauthorized');
                } else {
                    // if user logged in, redirect to URL
                    $this->redirect($_SERVER['REQUEST_URI']);
                }
            } else {
                header('WWW-Authenticate: Basic realm="' . __SITE__ . '"');
                header('HTTP/1.0 401 Unauthorized');
            }
        } else {
            // display error message if user is logged in but does not have enough permissions
            $this->forbidden();
        }
    }

    private function forbidden() {

        $this->outputType = 'JSON';
        $this->error('I\'m sorry Dave, I\'m afraid I can\'t do that.', 403);
        $this->output();
    }

}
