# Core Object

Every class in ohCRUD inherits from the `Core` object. This class is responsible for setting the output type and http headers and performing tasks like logging, error handling, session management, caching, encryption, and debugging.

## Properties

| Property | Type | Description |
| --- | --- | --- |
| `data` | mixed | This property holds results of a remote request or database query or any data. |
| `errors` | array | If any errors occur it will be included in this element. |
| `success` | boolean | This is set to `TRUE` if the operation was successful or `FALSE` otherwise. |
| `outputType` | string / null | Output can be set to `'HTML'`, `'JSON'` or `NULL`. The constants `Core::OUTPUT_HTML`, `Core::OUTPUT_JSON`, `Core::OUTPUT_NULL` can be used. |
| `outputHeaders` | array | You can set custom HTML headers here. |
| `outputHeadersSent` | boolean | This is set to `TRUE` if HTML headers have already been sent or `FALSE` otherwise. |
| `outputStatusCode` | integer | This is set automatically if the operation is successful or if any errors happen. You can set your own HTTP status code here as well. |
| `runtime` | float | Contains the script execution time in seconds. Only available when debug mode is on. |
| `version` | string | The version of ohCRUD. |

## Methods

All the methods in `Core` can be used in a chain, see the example below:

```php
$this->log('info', 'This is a test log.')->debug($_SERVER)->output();
```

| Method | Description | Return Value |
| --- | --- | --- |
| `setOutputType($outputType)` | Sets the output type for the response (e.g., 'HTML', 'JSON', or null). | `Core` Object |
| `setOutputHeaders($outputHeaders = [])` | Sets custom HTTP headers for the response. | `Core` Object |
| `setOutputStatusCode($outputStatusCode)` | Sets the HTTP status code for the response. | `Core` Object |
| `setSession($key, $value)` | Sets a session variable. | `Core` Object |
| `getSession($key)` | Gets a session variable. | mixed |
| `unsetSession($key)` | Unsets a session variable. | `Core` Object |
| `regenerateSession()` | Regenerates the session ID. | `Core` Object |
| `clearSession()` | Destroys the entire session. | `Core` Object |
| `CSRF()` | Generates and returns a CSRF token. | string |
| `checkCSRF($token)` | Validates a CSRF token. | boolean |
| `output()` | Generates and sends the response based on the `outputType`. | `Core` Object |
| `headers()` | Sends the HTTP headers. | `Core` Object |
| `redirect($url)` | Performs an HTTP redirect. | boolean |
| `getCache($key, $duration = 3600)` | Retrieves data from the cache. | mixed |
| `setCache($key, $data)` | Stores data in the cache. | boolean |
| `unsetCache($key)` | Removes data from the cache. | boolean |
| `encryptText($text, $password = '')` | Encrypts a string. | string |
| `decryptText($encryptedText, $password = '')` | Decrypts a string. | string |
| `log($level, $message, ...)` | Logs a message using Monolog. | `Core` Object |
| `error($message, $outputStatusCode = 500)` | Logs an error and sets the response to an error state. | `Core` Object |
| `registerCoreErrorHandlers()` | Registers custom error and exception handlers. | void |
| `coreErrorHandler($errno, $errstr, $errfile, $errline)` | Custom error handler to convert errors to exceptions. | |
| `coreExceptionHandler($exception)` | Custom exception handler for uncaught exceptions. | |
| `coreShutdownHandler()` | Shutdown function to handle fatal errors. | |
| `background($command, $wait = 0)` | Executes a CLI command in the background. | void |
| `console($message, $color, ...)` | Outputs a colored message to the console (CLI only). | boolean |
| `debug($expression, $label, ...)` | A wrapper for `php-ref` to debug variables. | `Core` Object |
