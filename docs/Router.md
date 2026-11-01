# Router Object

The router maps API prefixes in `__OHCRUD_ENDPOINTS__` to controller classes. The
last path segment selects an explicitly annotated method. Paths outside those
prefixes use `__OHCRUD_DEFAULT_PATH_HANDLER__` for CMS rendering.

## Endpoint access

```php
// Optional class restriction, applied in addition to each endpoint permission.
#[\ohCRUD\Permission(2)]
class Example extends \ohCRUD\DB {

    // Only permission levels 1 and 2 may save, and only using POST.
    #[\ohCRUD\Endpoint(permission: 2, methods: ['POST'])]
    public function save($request) {
        // Validate CSRF and input before changing data.
    }
}
```

`Endpoint` requires an integer permission. `__OHCRUD_PERMISSION_ALL__` permits
anonymous access; otherwise a signed-in user's numeric `PERMISSIONS` must be less
than or equal to the declared level. Class restrictions, including those declared
on parent classes, must also pass. A class attribute alone never exposes a method.

Omitting `methods` permits every HTTP verb. An explicit nonempty array restricts
verbs and must contain uppercase names, such as `['GET', 'POST']`. Unsupported
verbs receive **405** with an `Allow` header. `HEAD` is not implicitly added to
`GET`; declare it when supported. Allowed cross-origin preflights use the selected
endpoint's verbs without requiring authentication; a disallowed requested verb
receives 405. Origin, IP and Fetch Metadata policies remain in force.

Missing attributes, nonexistent methods, nonpublic/static methods, magic methods,
and bare API prefixes receive **404**, without constructing the controller or
falling through to the CMS. Annotated inherited methods remain exposed; overriding
one requires an attribute on the override. Invalid attribute arguments are
configuration errors and fail closed.

Anonymous callers of protected endpoints may authenticate using Basic Auth or a
`Token` header. Failed/missing authentication receives 401 or 403 according to the
existing authentication flow; an authenticated user lacking permission receives
403. Endpoint declarations do not replace CSRF validation in mutation handlers.

CLI routing also requires the endpoint attribute but bypasses HTTP verb and user
permission checks. CMS page permissions remain separate from API attributes.

## Migration from controller maps

Replace each `$permissions` method entry and corresponding `$methods` entry with
an `Endpoint` attribute directly above that method. Convert any restrictive
`'object'` permission into a class-level `Permission` attribute. The old maps are
no longer consulted: retaining them without attributes does not expose endpoints.
See `public/app/controllers/cExample.php` for public, protected and unrestricted-verb
examples. This is an intentional access-policy migration for custom controllers.

The router reflects only the selected method and its controller ancestry. It does
not scan every controller or build a filesystem policy cache on each request.
