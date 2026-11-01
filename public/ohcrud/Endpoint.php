<?php
namespace ohCRUD;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Explicitly expose a controller method and declare its request access policy.
#[\Attribute(\Attribute::TARGET_METHOD)]
class Endpoint {

    public $permission;
    public $methods;

    // Omitted methods allow every HTTP verb; permissions must always be explicit.
    public function __construct($permission, $methods = null) {
        if (is_int($permission) == false || ($permission !== __OHCRUD_PERMISSION_ALL__ && $permission < 1)) {
            throw new \InvalidArgumentException('Invalid endpoint permission.');
        }
        if ($methods !== null) {
            if (is_array($methods) == false || count($methods) === 0) {
                throw new \InvalidArgumentException('Endpoint methods must be a nonempty array.');
            }
            foreach ($methods as $method) {
                if (is_string($method) == false || preg_match('/^[A-Z]+$/D', $method) !== 1) {
                    throw new \InvalidArgumentException('Endpoint methods must be uppercase HTTP verbs.');
                }
            }
            $methods = array_values(array_unique($methods));
        }
        $this->permission = $permission;
        $this->methods = $methods;
    }
}
