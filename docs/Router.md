# Routing reference

[Documentation](DOCUMENTATION.md) · [Architecture](architecture.md) · [Authentication](Users.md)

Source: [Router.php](../public/ohcrud/Router.php), [Endpoint.php](../public/ohcrud/Endpoint.php), [Permission.php](../public/ohcrud/Permission.php), [routes.php](../public/routes.php).

## Mapping a URL

Add a prefix to the existing endpoint map:

```php
'/reports/' => 'app\controllers\cReports',
```

Then declare a method in that controller:

```php
// Return a report only to users at permission level 5 or lower.
#[\ohCRUD\Endpoint(permission: 5, methods: ['GET'])]
public function summary($request) {
    $this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);
    $this->data = ['year' => date('Y')];
    return $this->output();
}
```

`GET /reports/summary/` selects `cReports::summary()`. Paths normalize to a trailing slash. The map is literal: no named parameters, regex routes, or wildcard resource binding. Use query/body fields for identifiers. An exact prefix request such as `/reports/` has no selected method and returns 404; there is no implicit `index()` action. Keep route keys and class filenames case-correct.

## Endpoint and class policy

`Endpoint($permission, $methods = null)` accepts an integer permission of `-1` or at least `1` and must appear exactly once on a public, non-static method. Magic methods and methods without the attribute cannot be dispatched. Omitting `methods` allows all HTTP verbs; declare verbs for new handlers. The constructor requires a nonempty array of uppercase verbs when provided.

A controller can add a class restriction:

```php
#[\ohCRUD\Permission(1)]
class cReports extends \ohCRUD\DB {
    // Add annotated methods here.
}
```

Every class restriction, including those inherited from parent controllers, and the selected endpoint restriction must pass. A public method attribute does not override a protected class. Numeric permissions admit authenticated users whose `PERMISSIONS <= required level`. `__OHCRUD_PERMISSION_ALL__` (`-1`) means public; `PHP_INT_MAX` is used for authenticated-account operations.

Legacy `$permissions` arrays are not the router's authorization mechanism. Endpoint attributes are still required on CLI calls.

## Request parsing

| Input | Controller access |
| --- | --- |
| `?id=42` or ordinary form field | `$request->id` |
| JSON object body with JSON Content-Type | `$request->payload->ID` |
| Other API body | `$request->payload` is the raw body string |
| Multipart upload | `$_FILES`; ordinary fields on `$request` |
| CLI path query | Top-level properties parsed with `parse_str()` |

Malformed JSON, JSON arrays, and scalar JSON bodies return HTTP 400 before the handler. Validate each field's type; query parameters can be arrays. `$request` starts from PHP's `$_REQUEST`, so its merged sources also depend on PHP configuration. Do not assume it contains only query parameters.

## HTTP access checks

For matched API endpoints, the router checks Fetch Metadata, optional IP filtering, origin policy, declared verbs, and permissions before construction. Cookie-bearing requests with `Sec-Fetch-Site: cross-site` are rejected. Missing Origin/Referer is accepted for non-browser use; origin checks are not authentication.

Same-origin calls are accepted using the detected request scheme and configured host. Otherwise `__OHCRUD_ALLOWED_ORIGINS__` contains exact origin strings, including scheme and port. Allowed cross-origin responses echo the origin and set `Vary: Origin`; credentials are enabled only for explicitly listed origins. Disabling origin filtering permits arbitrary origins but does not automatically enable credentials for them. Cross-origin preflight checks the endpoint verb declaration. The allowed headers include `Content-Type`, `Authorization`, and `Token`.

CSRF token generation occurs here, but **validation belongs in the handler**. Use [the write-endpoint pattern](models-and-controllers.md#controller-validation-and-writes). Debug mode does not bypass `checkCSRF()`.

## Authentication and errors

A valid session is checked first. When a protected route lacks one, the router can authenticate HTTP Basic credentials or a `Token` header. It then rechecks permission thresholds. Existing insufficient sessions are denied rather than silently replaced with header credentials. Public routes do not trigger header authentication just because a token was supplied.

| Status | Common cause |
| --- | --- |
| 400 | JSON body is not an object or handler validation fails |
| 401 | Protected request lacks successful Basic/session authentication |
| 403 | Access policy, insufficient permissions, or invalid token |
| 404 | Unknown/unannotated method or missing CMS page |
| 405 | Verb not declared; response includes `Allow` |

Earlier policy checks can reject a request before a verb error is reached. Not every router error has the same body shape; inspect the HTTP status first.

## CLI

```sh
php public/index.php '/greeting/hello/?name=Ada'
```

CLI dispatch bypasses user permission, HTTP method, IP, origin, and Fetch Metadata restrictions. It does not bypass the endpoint attribute requirement or validations written inside the handler. In particular, a JSON/CSRF-oriented web handler does not automatically become a useful CLI command. Use dedicated CLI scripts for provisioning, and never expose arbitrary CLI route execution to untrusted input.

Continue with [controller patterns](models-and-controllers.md) or [CMS fallback rendering](cms-and-admin.md).
