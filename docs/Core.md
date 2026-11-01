# Core reference

[Documentation](DOCUMENTATION.md) · [Architecture](architecture.md) · [Models and controllers](models-and-controllers.md)

Source: [Core.php](../public/ohcrud/Core.php). Core supplies response and utility behavior to framework classes. Not every class inherits it: attributes, `Migrations`, and the `mContent` data object have separate roles.

## Response state

| Property | Default and purpose |
| --- | --- |
| `$data` | Response/query value, initially null |
| `$success` | `true`; errors set false |
| `$errors` | Empty array of messages |
| `$outputType` | `'HTML'`; constants `OUTPUT_HTML`, `OUTPUT_JSON`, `OUTPUT_NULL` |
| `$outputStatusCode` | `200`; assign directly for 201/404/etc. |
| `$outputHeaders` | Header strings queued by `includeOutputHeader()` |
| `$outputHeadersSent` | Prevents duplicate framework header emission |
| `$runtime`, `$version` | Diagnostic runtime and framework version |

```php
$this->setOutputType(\ohCRUD\Core::OUTPUT_JSON);
$this->outputStatusCode = 201;
$this->includeOutputHeader('Location: /notes/show/?id=' . $id);
$this->data = ['ID' => $id];
return $this->output();
```

There are no `setOutputHeaders()` or `setOutputStatusCode()` methods. `setOutputType()`, `includeOutputHeader()`, `headers()`, and `output()` return `$this`. HTML output prints only string data. Null output suppresses normal response headers/body. JSON output serializes object state and excludes known internal fields such as connection/configuration/header state; it is not a DTO allowlist. Keep secrets and services out of serializable properties and select fields before assigning `$data`.

Send output once, after preparing the complete response. Earlier output or headers can prevent JSON serialization. `headers()` sets the configured status, anti-cache headers, frame policy, and nosniff. `output()` does not exit; use `return` to stop a handler.

## Errors and logs

```php
if ($record === false) {
    return $this->error('Record not found.', 404)->output();
}
```

`error($message, $outputStatusCode = 400)` sets failure state, appends the message, and normally logs it (403/404 are not logged by this helper). It does not throw or output. Core error handlers turn uncaught failures into a generic 500 response while writing details privately. Debug mode enables more diagnostics and must not be used for public production traffic.

```php
$this->log('info', 'Report generated.', ['reportId' => $id]);
```

`log($level, $message, $context = [], $channel = 'system', $logFile = 'app.log')` uses Monolog JSON records and returns `$this`. The log directory must be writable. Do not log credentials, tokens, or full sensitive request bodies. Log filenames should come from application code, not request input.

## Sessions and CSRF

| Method | Result |
| --- | --- |
| `setSession($key, $value)` | `$this`; opens and closes the write session |
| `getSession($key)` | Stored value or `false`; opens the session and does not close it |
| `unsetSession($key)` | `$this`; removes a key and closes the session |
| `regenerateSession()` | `$this`; rotates the ID and closes the session |
| `clearSession()` | `$this`; destroys the session; not the complete application logout flow |
| `CSRF()` | Current session token, generating one if absent |
| `checkCSRF($token)` | Boolean, constant-time comparison of nonempty strings |

The bootstrap closes the session early to reduce lock contention. Prefer the mutation helpers rather than changing `$_SESSION` after it has been closed. If calling `getSession()`, release the lock with `session_write_close()` when finished. Use the [logout endpoint](Users.md) to clear all authentication/challenge state. Debug mode does not bypass CSRF.

## Cache

```php
$summary = $this->getCache('public-summary:v1', 60);
if ($summary === false) {
    $summary = ['publishedCount' => 12]; // Replace with an authorized query.
    $this->setCache('public-summary:v1', $summary);
}
```

| Method | Result |
| --- | --- |
| `getCache($key, $duration = 3600)` | JSON-decoded data or `false` for disabled/missing/expired/invalid entries |
| `setCache($key, $data)` | Written byte count or `false`; atomic JSON publication |
| `unsetCache($key)` | Boolean indicating whether an existing file was removed |

Object-like JSON decodes as objects; array/object shape may differ from the original PHP value. A cached boolean false is indistinguishable from a miss. TTL is supplied when reading, not stored as a per-entry expiration on write. Names use a hash of application name plus key; file contents are not encrypted. Use a private directory, version your keys, and invalidate after successful writes. Authorization-dependent data requires authorization-aware keys and revalidation; the CMS intentionally caches only eligible purified Markdown fragments.

## Escaping, URLs, and redirects

| Method | Purpose |
| --- | --- |
| `escapeHTML($value)` | Escapes scalar values for HTML text/quoted attributes; non-scalars become empty strings |
| `safeURL($value)` | Allows ordinary relative URLs and HTTP/HTTPS/mailto/tel, rejects unsafe schemes/control characters; escape the result for HTML |
| `localRedirect($url)` | Validates a local absolute return path, otherwise `/` |
| `redirect($url)` | Sends Location and exits if headers are available, otherwise returns false |

```php
$this->redirect($this->localRedirect($request->redirect ?? '/'));
```

`redirect()` alone is not URL validation. Use trusted destinations or `localRedirect()`.

## Other utilities

`encryptText($text, $password = '')` and `decryptText($encryptedText, $password = '')` implement the framework's existing AES-256-CBC format using the application secret. The format does not provide an authentication tag; it is not a general-purpose signed-message or tamper-proof storage API.

`background($route, $wait = 0)` starts a detached PHP CLI route on a Unix-like shell, optionally after a delay. It discards output and has no durable queue, retry, or job status. `console($message = '', $color = 'WHT', $shouldAddNewLine = true)` prints colored CLI text. `debug($expression = null, $label = null, $showLineNumber = true)` is a development inspection helper returning `$this`; do not expose it to untrusted users.

See [routing's CLI boundary](Router.md#cli) and [operations](deployment-and-testing.md).
