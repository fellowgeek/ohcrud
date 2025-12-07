# Router Object

The `Router` object is your interface to the outside world, it will take a `path` which can be a `URI` or a `console argument` and will call upon the appropriate class and method to handle the request.

To accomplish this task, the Router will look at the `__OHCRUD_ENDPOINTS__` constant and will call the class and method based on the defined namespace. If the path was not defined in `__OHCRUD_ENDPOINTS__`, then the Router will look at `__OHCRUD_DEFAULT_PATH_HANDLER__` and will route the request to the `defaultPathHandler` method of the class defined here.

If the Router fails to find a path after trying the steps above, it will return a 404 error.

## Permissions

Before calling objects and methods the Router checks the target class for the public `$permissions` array property.

```php
namespace app\models;

class Example extends \ohCRUD\DB {

    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'exampleMethodA' => 2,
        'exampleMethodB' => 7
    ];

    // ...
}
```

If a user is present in the `$_SESSION`, the Router will check that user's `PERMISSIONS` value against the number assigned to the target method in the `$permissions` array. If the user's `PERMISSIONS` value is *smaller than or equal to* the method's value it will grant access, otherwise it will return a 403 error.

The `'object'` key in the `$permissions` array controls the global access to the object.

To grant full access to a method or object without requiring the user to login, use the `__OHCRUD_PERMISSION_ALL__` constant.

## CORS and Security

The router automatically handles Cross-Origin Resource Sharing (CORS) and IP filtering based on the configuration in `routes.php`. It also handles CSRF token generation and validation.

## Routing Logic

The `route()` method is the core of the router. It performs the following steps:

1.  **Processes Request**: It gathers request data from `php://input`, `$_REQUEST`, `$_GET`, `$_POST`, or command-line arguments.
2.  **Endpoint Matching**: It first tries to match the full request path against the `__OHCRUD_ENDPOINTS__` array.
3.  **Method Matching**: If a full path match is not found, it treats the last part of the path as a method name and tries to match the base path against `__OHCRUD_ENDPOINTS__`.
4.  **Default Handler**: If no endpoint matches, it falls back to the `__OHCRUD_DEFAULT_PATH_HANDLER__` and calls its `defaultPathHandler` method.
5.  **404 Error**: If no route is found, it returns a 404 error.

Before executing a route, it performs permission checks and handles authorization via Basic Auth or a `Token` header if required.
