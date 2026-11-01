<?php
namespace ohCRUD;

// Prevent direct access to this class
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Class Router - routing operations class for ohCRUD, all incoming request processed by this class
class Router extends \ohCRUD\Core {

    private $request;
    private $requestMethod;

    // Constructor for the Router class. It sets up routing based on the provided URL path.
    public function __construct($rawPath = null) {

        // Extract the path from the provided URL and store it in global variables.
        $GLOBALS['PATH'] = rtrim(parse_url($rawPath, PHP_URL_PATH) ?? '', '/') . '/';
        $GLOBALS['PATH_ARRAY'] = preg_split('[/]', $GLOBALS['PATH'], 0, PREG_SPLIT_NO_EMPTY);

        // Register the core error handlers.
        $this->registerCoreErrorHandlers();

        // Expire stale accounts and permissions before any API or CMS handling.
        if (isset($_SESSION['User']) == true) {
            (new \ohCRUD\Users)->validateSession();
        }

        // Call the route method to process the request.
        $this->route($rawPath);
    }

    // The route method processes incoming requests and handles routing.
    public function route($rawPath = null) {

        // Variables
        $path = $GLOBALS['PATH'];
        $pathArray = $GLOBALS['PATH_ARRAY'];
        $matchedObject = null;
        $matchedMethod = null;

        // Define Environment
        $isCLI = (PHP_SAPI === 'cli');

        // ---------------------------------------------------
        // STEP 1: Find Match
        // ---------------------------------------------------
        if (isset(__OHCRUD_ENDPOINTS__[$path]) == true) {
            // Strategy A: exact path match
            $matchedObject = __OHCRUD_ENDPOINTS__[$path];
        }
        else {
            // Strategy B: base path + method match
            $methodCandidate = array_pop($pathArray);
            $basePath = '/' . implode('/', $pathArray) . '/';

            if (isset(__OHCRUD_ENDPOINTS__[$basePath]) == true) {
                $matchedObject = __OHCRUD_ENDPOINTS__[$basePath];
                $matchedMethod = $methodCandidate;
            }
        }

        // ---------------------------------------------------
        // STEP 2: Execute Match (API)
        // ---------------------------------------------------
        if (isset($matchedObject) == true) {

            // Only explicitly annotated public instance methods are routable.
            $policy = $this->getEndpointPolicy($matchedObject, $matchedMethod);
            if ($policy === false) {
                return $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON)->error('Endpoint not found.', 404)->output();
            }
            $allowedMethods = $policy['methods'];

            // Build Request (Parse Payload: True)
            if ($this->setRequest($isCLI, $rawPath, true) === false) {
                return $this;
            }

            // Security Checks (Skip for CLI)
            if ($isCLI == false) {
                if ($this->isRequestAllowedByFetchMetadata() == false) {
                    $this->forbidden();
                    return $this;
                }
                if ($this->isRequestAllowedByAccessPolicy($allowedMethods) == false) {
                    $this->forbidden();
                    return $this;
                }
            }

            // Enforce declared verbs before invoking a handler or authenticating it.
            if ($isCLI == false && $allowedMethods !== null && in_array($this->requestMethod, $allowedMethods, true) == false) {
                $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);
                $this->includeOutputHeader('Allow: ' . implode(', ', $allowedMethods));
                return $this->error('Method not allowed.', 405)->output();
            }

            // Authenticate once, then recheck both permission levels without routing recursively.
            if ($this->checkPermissions($policy['permissions']) == false) {
                if (isset($_SESSION['User']) == true) {
                    $this->forbidden();
                    return $this;
                }
                if ($this->authorize() == false) {
                    return $this;
                }
                if ($this->checkPermissions($policy['permissions']) == false) {
                    $this->forbidden();
                    return $this;
                }
            }

            // Construct the controller only after its access policy has passed.
            $object = new $matchedObject;
            $object->$matchedMethod($this->request);
            return $this;
        }

        // ---------------------------------------------------
        // STEP 3: Default Handler (CMS)
        // ---------------------------------------------------
        if (defined('__OHCRUD_DEFAULT_PATH_HANDLER__') == true && __OHCRUD_DEFAULT_PATH_HANDLER__ != '') {
            $class = __OHCRUD_DEFAULT_PATH_HANDLER__;

            // Build Request (Parse Payload: False - CMS handles its own)
            $this->setRequest($isCLI, $rawPath, false);

            $object = new $class($this->request);
            if (method_exists($object, 'defaultPathHandler') == true) {
                $object->defaultPathHandler($GLOBALS['PATH']);
                return $this;
            }
        }

        // 404 Not Found
        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);
        $this->error('ohCRUD! You just got 404\'d.', 404);
        $this->output();
    }

    // Helper to consolidate request creation
    private function setRequest($isCLI, $rawPath, $parsePayload = false) {
        if ($isCLI == true) {
            $parameters = [];
            parse_str(parse_url($rawPath, PHP_URL_QUERY) ?? '', $parameters);
            $this->request = (object) $parameters;
            $this->requestMethod = 'CLI';
        } else {
            $this->request = (object) $_REQUEST;
            $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

            // Only read/decode input if explicitly requested (API only)
            if ($parsePayload == true) {
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                $input = file_get_contents('php://input');

                if (stripos($contentType, 'json') !== false) {
                    $this->request->payload = json_decode($input);
                    if (json_last_error() !== JSON_ERROR_NONE || is_object($this->request->payload) == false) {
                        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);
                        $this->error('Expected a JSON object.', 400)->output();
                        return false;
                    }
                } else {
                    $this->request->payload = $input;
                }
            }
        }
    }

    // Handle CORS (Cross-Origin Resource Sharing) and access policies for incoming requests.
    private function isRequestAllowedByAccessPolicy($allowedMethods) {

        // Set up CSRF (Cross-Site Request Forgery) token.
        $this->CSRF();

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
        if ($origin === ($_SERVER['REQUEST_SCHEME'] ?? '') . '://' . __SITE__) {
            return true;
        }

        // If no origin is provided, allow access (e.g., direct API calls, non-browser clients).
        if ($origin === '') {
            return true;
        }

        // Handle cross-origin requests and set appropriate CORS headers for allowed origins.
        if (in_array($origin, __OHCRUD_ALLOWED_ORIGINS__) == true || __OHCRUD_ALLOWED_ORIGINS_ENABLED__ == false) {

            // Set CORS headers
            header('Vary: Origin');
            header('Access-Control-Allow-Origin: ' . $origin);
            if (in_array($origin, __OHCRUD_ALLOWED_ORIGINS__, true) == true) {
                header('Access-Control-Allow-Credentials: true');
            }
            header('Access-Control-Max-Age: 86400');

            // Preflight request handling
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                $preflightMethod = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? '';
                if ($allowedMethods !== null && in_array($preflightMethod, $allowedMethods, true) == false) {
                    $this->includeOutputHeader('Allow: ' . implode(', ', $allowedMethods));
                    $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON)->error('Method not allowed.', 405)->output();
                    exit;
                }
                header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods ?? [$preflightMethod]));
                header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, Token");
                die();
            }
            return true;
        } else {
            return false;
        }
    }

    // Check Fetch Metadata headers to prevent CSRF attacks.
    private function isRequestAllowedByFetchMetadata() {
        // If no cookies are sent, CSRF is not applicable (API / non-browser client)
        if (empty($_COOKIE) == true) {
            return true;
        }

        // Missing header = unknown (Safari, older browsers), allow
        if (empty($_SERVER['HTTP_SEC_FETCH_SITE']) == true) {
            return true;
        }

        // Cookie-authenticated + cross-site = almost certainly CSRF
        if ($_SERVER['HTTP_SEC_FETCH_SITE'] === 'cross-site') {
            return false;
        }

        return true;
    }

    // Resolve only the selected method; inherited helpers stay private to the framework.
    private function getEndpointPolicy($class, $method) {
        if ($method === null || strncmp($method, '__', 2) === 0 || method_exists($class, $method) == false) {
            return false;
        }
        $reflection = new \ReflectionMethod($class, $method);
        if ($reflection->isPublic() == false || $reflection->isStatic() == true) {
            return false;
        }
        $attributes = $reflection->getAttributes(\ohCRUD\Endpoint::class);
        if (count($attributes) !== 1) {
            return false;
        }
        $endpoint = $attributes[0]->newInstance();
        $permissions = [$endpoint->permission];

        // Parent controller restrictions remain in force in subclasses.
        $controller = new \ReflectionClass($class);
        do {
            foreach ($controller->getAttributes(\ohCRUD\Permission::class) as $attribute) {
                $permissions[] = $attribute->newInstance()->permission;
            }
            $controller = $controller->getParentClass();
        } while ($controller !== false);

        return ['methods' => $endpoint->methods, 'permissions' => $permissions];
    }

    // Require every class and method restriction to pass; CLI keeps its permission bypass.
    private function checkPermissions($permissions) {
        if (PHP_SAPI === 'cli') return true;

        $userPermissions = isset($_SESSION['User']->PERMISSIONS) == true ? (int) $_SESSION['User']->PERMISSIONS : false;
        foreach ($permissions as $permission) {
            if ($permission !== __OHCRUD_PERMISSION_ALL__ &&
                ($userPermissions === false || $userPermissions > $permission)) {
                return false;
            }
        }
        return true;
    }

    // Handle user authorization.
    private function authorize() {

        // Variables
        $users = new \ohCRUD\Users;
        $httpHeaders = array_change_key_case(getallheaders(), CASE_LOWER);
        $userHasLoggedIn = false;

        // Authenticate the user using basic authentication or a token.
        if (isset($_SERVER['PHP_AUTH_USER']) == true || isset($_SERVER['PHP_AUTH_PW']) == true || isset($httpHeaders['token']) == true) {
            $userHasLoggedIn = $users->login($_SERVER['PHP_AUTH_USER'] ?? null, $_SERVER['PHP_AUTH_PW'] ?? null, $httpHeaders['token'] ?? null);
        }
        if ($userHasLoggedIn !== false && isset($_SESSION['User']) == true) {
            return true;
        } else {
            if (isset($httpHeaders['token']) == true) {
                $this->forbidden();
            } else {
                // Unauthorized access - Send HTTP headers for basic authentication.
                $this->outputStatusCode = 401;
                $this->includeOutputHeader('WWW-Authenticate: Basic realm="' . __SITE__ . '"');
                $this->includeOutputHeader('HTTP/1.0 401 Unauthorized');
                $this->output();
            }
        }
        return false;
    }

    // Handle forbidden access and return a 403 error response.
    private function forbidden() {

        $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);
        $this->error('I\'m sorry Dave, I\'m afraid I can\'t do that.', 403);
        $this->output();
    }

}
