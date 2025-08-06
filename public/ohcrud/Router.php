<?php
namespace ohCRUD;

// Prevent direct access to this class
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Class Router - routing operations class for ohCRUD, all incoming request processed by this class
class Router extends \ohCRUD\Core {

    private $request;

    // Constructor for the Router class. It sets up routing based on the provided URL path.
    public function __construct($rawPath = null) {

        // Extract the path from the provided URL and store it in global variables.
        $GLOBALS['PATH'] = rtrim(parse_url($rawPath, PHP_URL_PATH) ?? '', '/') . '/';
        $GLOBALS['PATH_ARRAY'] = preg_split('[/]', $GLOBALS['PATH'], 0, PREG_SPLIT_NO_EMPTY);

        // Register the core error handlers.
        $this->registerCoreErrorHandlers();

        // Call the route method to process the request.
        $this->route($rawPath);
    }

    // The route method processes incoming requests and handles routing.
    public function route($rawPath = null) {

        // Variables
        $path = $GLOBALS['PATH'];
        $pathArray = $GLOBALS['PATH_ARRAY'];
        $method = '';

        // Process command-line parameters if the script is run in CLI mode.
        if (PHP_SAPI === 'cli') {
            $parameters = [];
            parse_str(parse_url($rawPath, PHP_URL_QUERY) ?? '', $parameters);
            $this->request = (object) $parameters;
        } else {
            // Process API parameters if not in CLI mode.
            $this->request = (object) \array_merge($_REQUEST, $_GET, $_POST);
            $payload = file_get_contents('php://input');
            if (empty($payload) == false) {
                // Decode JSON payload if the content type is 'application/json'.
                if ($_SERVER['CONTENT_TYPE'] === 'application/json') $this->request->payload = \json_decode($payload); else $this->request->payload = $payload;
            }
        }

        // Try to create the object based on the path.
        if (array_key_exists($path, __OHCRUD_ENDPOINTS__) == true) {
            $objectName = __OHCRUD_ENDPOINTS__[$path];
            $object = new $objectName;

            // Check if the object has permissions, and if not, handle authorization or forbidden access.
            if (isset($object->permissions) == true && $this->checkPermissions($object->permissions) == false) {
                if (isset($_SESSION['User']) == false) $this->authorize(); else $this->forbidden();
            }

            return $this;
        }

        // If the object is not found, try to create it from the base path and call the object's method from the ending path.
        $method = array_pop($pathArray);
        $path = '/' . implode('/', $pathArray) . '/';

        if (array_key_exists($path, __OHCRUD_ENDPOINTS__) == true) {

            // Handle CORS (Cross-Origin Resource Sharing).
            if ($this->handleCORS() == false) {
                $this->forbidden();
                return $this;
            }

            $objectName = __OHCRUD_ENDPOINTS__[$path];
            $object = new $objectName;

            // Check if the object has permissions for the specific method and call the method.
            if (isset($object->permissions) == true && method_exists($object, $method) == true && $this->checkPermissions($object->permissions, $method) == true) {
                $object->$method($this->request);
            } else {
                if (isset($_SESSION['User']) == false) $this->authorize(); else $this->forbidden();
            }
            return $this;
        }

        // Redirect to the default path handler if available, otherwise return a 404 error.
        if (__OHCRUD_DEFAULT_PATH_HANDLER__ != '') {
            $objectName = __OHCRUD_DEFAULT_PATH_HANDLER__;
            $object = new $objectName($this->request);
            if (method_exists($object, 'defaultPathHandler') == true) {
                $object->defaultPathHandler($GLOBALS['PATH']);
                return $this;
            }
        }

        // Set the output type to JSON and return a 404 error response.
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);
        $this->error('ohCRUD! You just got 404\'d.', 404);
        $this->output();

    }

    // Check if the user has the necessary permissions for an operation.
    private function checkPermissions($expression, $method = null) {

        // Grant permission if the script is called from the command-line interface.
        if (PHP_SAPI === 'cli') return true;

        // Variables
        $objectHasPermission = false;
        $methodHasPermission = false;
        $userPermissions = (isset($_SESSION['User']->PERMISSIONS) == true) ? (int) $_SESSION['User']->PERMISSIONS : false;

        // Check object permission
        if (isset($expression['object']) == true) {
            if ((int) $expression['object'] == __OHCRUD_PERMISSION_ALL__ || ((int) $expression['object'] >= $userPermissions && $userPermissions !== false))
                $objectHasPermission = true;
        }

        // Check method permission
        if (isset($method) == true && isset($expression[$method]) == true) {
            if ((int) $expression[$method] == __OHCRUD_PERMISSION_ALL__ || ($expression[$method] >= $userPermissions && $userPermissions !== false))
                $methodHasPermission = true;
        } else {
            $methodHasPermission = false;
        }

        return ($objectHasPermission && $methodHasPermission);
    }

    // Handle user authorization.
    private function authorize() {

        // Variables
        $users = new \ohCRUD\Users;
        $httpHeaders = getallheaders();
        $userHasLoggedIn = false;

        // Authenticate the user using basic authentication or a token.
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
                    // Unauthorized access - Send HTTP headers for basic authentication.
                    header('WWW-Authenticate: Basic realm="' . __SITE__ . '"');
                    header('HTTP/1.0 401 Unauthorized');
                }
                die();
            }
        }
    }

    // Handle Cross-Origin Resource Sharing (CORS) to allow or deny access from different origins.
    private function handleCORS() {

        // Set up CSRF (Cross-Site Request Forgery) token.
        $this->CSRF();

        // Skip CORS check if we are in CLI mode.
        if (PHP_SAPI === 'cli') return true;

        // Check if remote IP filtering is enabled and handle allowed IPs.
        if (__OHCRUD_ALLOWED_IPS_ENABLED__ == true) {
            if (in_array($_SERVER['REMOTE_ADDR'], __OHCRUD_ALLOWED_IPS__) == false) return false;
        }

        // Handle same origin requests using HTTP_ORIGIN and HTTP_REFERER to determine the request's origin.
        $origin = strtolower($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin === '')  {
            if ($parts = parse_url(strtolower(($_SERVER['HTTP_REFERER'] ?? '')))) {
                if (isset($parts['scheme']) == true && isset($parts['host']) == true)
                $origin = $parts['scheme'] . '://' . $parts['host'];
            }
        }

        // If the origin is not set or the request is coming from the current site, allow access.
        if ($origin === ($_SERVER['REQUEST_SCHEME'] ?? '') . '://' . __SITE__) return true;
        if ($origin === '') return true;

        // Handle cross-origin requests and set appropriate CORS headers.
        if (in_array($origin, __OHCRUD_ALLOWED_ORIGINS__) == true || ___OHCRUD_ALLOWED_ORIGINS_ENABLED__ == false) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
                header("Access-Control-Allow-Headers: token, Content-Type, Accept, Origin");
                die();
            }
            return true;
        } else {
            return false;
        }
    }

    // Handle forbidden access and return a 403 error response.
    private function forbidden() {

        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);
        $this->error('I\'m sorry Dave, I\'m afraid I can\'t do that.', 403);
        $this->output();
    }

}
