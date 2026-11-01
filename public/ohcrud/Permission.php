<?php
namespace ohCRUD;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Optionally restrict a controller without exposing any of its methods.
#[\Attribute(\Attribute::TARGET_CLASS)]
class Permission {

    public $permission;

    // Use the same permission levels as endpoint declarations.
    public function __construct($permission) {
        if (is_int($permission) == false || ($permission !== __OHCRUD_PERMISSION_ALL__ && $permission < 1)) {
            throw new \InvalidArgumentException('Invalid controller permission.');
        }
        $this->permission = $permission;
    }
}
