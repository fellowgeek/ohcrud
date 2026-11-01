# Authentication and permissions

[Documentation](DOCUMENTATION.md) · [Routing](Router.md) · [Admin API](Admin.md)

Source: [Users.php](../public/ohcrud/Users.php), [cUsers.php](../public/app/controllers/cUsers.php). Run [migrations](Migrations.md) before using authentication, and [provision an administrator](getting-started.md#create-the-first-administrator). No default credentials are installed.

## Permission model

`Users.PERMISSIONS` is a numeric threshold, not a collection of named roles. Lower numbers have broader access. A protected endpoint with permission 5 admits authenticated users at levels 1 through 5; `-1` is the special public endpoint/page value. Both endpoint and class restrictions must pass. `GROUP` is metadata and does not automatically authorize routes.

The CMS admin interface specifically requires integer permission 1. Ordinary application roles, ownership, and tenant boundaries must be implemented explicitly. A route-level threshold does not filter database rows.

## Browser login

1. Load `/login/` to establish a session and obtain its CSRF token through the rendered JavaScript.
2. POST a JSON object to `/ohcrud/users/login/` with `USERNAME`, `PASSWORD`, and `CSRF`; optional `REDIRECT` must be a local path.
3. If `data.TOTP` is active and `data.TOTPVerified` is false, complete the pending challenge with `/ohcrud/users/verify/`, sending `TOTP` as a six-digit string and the same session's `CSRF`.
4. After full authentication, `$_SESSION['User']` represents the signed-in user. Logout is POST `/ohcrud/users/logout/` with `CSRF`.

```javascript
const response = await fetch('/ohcrud/users/login/', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        CSRF: __CSRF__, USERNAME: username, PASSWORD: password, REDIRECT: '/'
    })
});
const result = await response.json();
```

A valid password for an MFA account establishes `tempUser`, not a fully authorized `User` session. Do not interpret password-stage `loggedIn` alone as completed MFA; follow `TOTP`/`TOTPVerified` and complete verification. A fresh login attempt supersedes an older challenge. Redirect helpers reject external/protocol-relative destinations.

## Sessions and throttling

Cookies are HttpOnly and SameSite=Lax; Secure is configurable and should be enabled on HTTPS. Session IDs rotate at authentication transitions. Authentication has both absolute and idle limits equal to `__OHCRUD_SESSION_LIFETIME__`. The router revalidates the account before API/CMS handling.

Changes to password, username, token, TOTP secret/state, status, or permission invalidate the session fingerprint and pending challenges. Session user data excludes password, token, hash, and TOTP secret.

`AuthState` stores shared counters and TOTP replay state. Missing state storage fails authentication closed. Password attempts are limited to 10 per account per 15 minutes; TOTP attempts to 5; source-IP failures to 100. Factor-management password checks have their own five-attempt limit. Successful authentication clears relevant account counters; successful credentials release their own IP reservation rather than unrelated failures. Challenges and pending authenticator replacements expire after five minutes. Accepted TOTP steps cannot be reused across sessions.

## API authentication

Protected routes can use HTTP Basic or a `Token` header. Tokens have an 80-character lowercase hexadecimal form combining the user's HASH and decrypted token. Admin token operations can retrieve/rotate them. Do not construct tokens from usernames or expose stored encrypted values as credentials.

```sh
curl -H "Token: $OHCRUD_API_TOKEN" https://ohcrud.test/example/protectedEndPoint/
```

Use HTTPS. Token authentication deliberately bypasses TOTP and therefore has the authority of the account. Rotate tokens when compromised. A public endpoint does not automatically authenticate supplied headers; the router only invokes header authentication when its permission checks require it.

Basic authentication cannot complete an MFA challenge by itself. Built-in write endpoints still require CSRF even with token/Basic authentication; authentication is not a CSRF exemption. For automation, retain the authenticated session cookie and obtain a token for that same session through the CMS before calling those handlers, or design a dedicated, explicitly secured machine API.

## Authenticator management

Signed-in users manage their own factor at `/security/`:

| Endpoint | JSON fields in addition to `CSRF` |
| --- | --- |
| `/ohcrud/users/beginTOTP/` | `PASSWORD`; existing `TOTP` code when already enabled |
| `/ohcrud/users/confirmTOTP/` | `TOTP` code from the pending new authenticator |
| `/ohcrud/users/disableTOTP/` | `PASSWORD` and current `TOTP` code |

Enrollment returns a provisioning URI in `data.QR_CODE`. The existing authenticator stays active until the replacement is confirmed. Existing secrets are never redisplayed through admin endpoints, and generic user-table writes cannot change factor state. There is no built-in recovery-code flow or unrestricted admin factor reset; lost-factor recovery needs a separately controlled operational process.

## PHP API

| Method | Contract |
| --- | --- |
| `new Users($initializeSchema = false)` | Initializes schema in debug mode or with explicit CLI provisioning |
| `create($table, $data = [])` | Fluent; hashes PASSWORD and generates an encrypted token when USERNAME is supplied |
| `update($table, $data, $where, $bind = [])` | Fluent; hashes a supplied PASSWORD |
| `login($username, $password, $token = null)` | User/challenge object or `false`; may establish pending MFA rather than a full session |
| `verify($id, $TOTP_CODE)` | Completes a matching pending challenge; user object or `false` |
| `validateSession()` | Boolean; removes stale/invalid authentication state |
| `beginTOTP($password, $code = null)` | Provisioning-URI object or `false` |
| `confirmTOTP($code)`, `disableTOTP($password, $code)` | Result or `false`; use the account flow |
| `enableTOKEN($id)` | Boolean; rotates the token for an existing user, false on failure |
| `enableTOTP($id, $activateTOTP = true)` | Legacy method always returns `false` |

Use `Users` rather than generic DB writes when application code changes plaintext passwords. Its hashing helpers are not a complete input-validation layer; validate fields and authorization first. Password hashing uses bcrypt with the application secret appended, and token/factor encryption also depends on that secret. Secret rotation is an account/data migration, not a routine configuration refresh. Back it up securely with your recovery process.

Continue with [deployment and testing](deployment-and-testing.md).
