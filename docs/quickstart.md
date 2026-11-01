# Five-minute quickstart

[Documentation](DOCUMENTATION.md) · Prerequisite: [configured installation](getting-started.md)

Build a greeting API and a CMS page. Allow about five minutes **after** dependencies, configuration, schema, and your development server are ready. The API works with the loopback smoke server; use Compose for the fully styled page.

## 1. Add a controller

Create `public/app/controllers/cGreeting.php`:

```php
<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Return a greeting without requiring a database connection.
class cGreeting extends \ohCRUD\Core {

    // Expose this method publicly for GET requests only.
    #[\ohCRUD\Endpoint(permission: __OHCRUD_PERMISSION_ALL__, methods: ['GET'])]
    public function hello($request) {
        $this->setOutputType(self::OUTPUT_JSON);

        // Validate query input before using it in the response.
        $name = $request->name ?? 'developer';
        if (is_string($name) == false || strlen($name) > 80) {
            return $this->error('Name must be a string of at most 80 bytes.')->output();
        }

        $this->data = ['message' => 'Hello, ' . trim($name) . '!'];
        return $this->output();
    }
}
```

## 2. Register the route

Add this entry **inside the existing** `__OHCRUD_ENDPOINTS__` array in `public/routes.php`:

```php
'/greeting/' => 'app\controllers\cGreeting',
```

The route prefix selects a class and the final segment selects an annotated method. No Composer regeneration is needed for a new class under the existing `app\` mapping.

```sh
curl -i 'http://127.0.0.1:8000/greeting/hello/?name=Ada'
```

With Compose, use `https://ohcrud.test/greeting/hello/?name=Ada`. Expect HTTP 200 with JSON containing:

```json
{"data":{"message":"Hello, Ada!"},"errors":[],"success":true}
```

This shows the relevant fields, not the entire response: Core also serializes framework state such as `outputStatusCode` and `version`.

## 3. Add a rendered page

Create `public/app/views/cms/welcome.phtml`:

```php
<?php
// Prevent direct access to this template.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }
$name = $this->request->name ?? 'developer';
?>
<h1>Welcome, <?php print($this->escapeHTML($name)); ?>!</h1>
<p>Your first ohCRUD page is running.</p>
<p><a href="/greeting/hello/?name=Ada">Read the greeting API</a></p>
```

Visit `/welcome/?name=Ada`. The CMS finds the file automatically and wraps it in the default theme. There is no route entry for this page. File views take precedence over database pages at the same path and are maintained in code rather than edited through the CMS.

## 4. Check success and failure behavior

```sh
php -l public/app/controllers/cGreeting.php
php -l public/app/views/cms/welcome.phtml
curl -i -X POST http://127.0.0.1:8000/greeting/hello/
curl -i 'http://127.0.0.1:8000/greeting/hello/?name[]=Ada'
curl -i http://127.0.0.1:8000/greeting/output/
```

Expect 405 for POST, 400 for array input, and 404 for the unannotated inherited helper. Use the HTTPS base URL for Compose. The checks assume direct requests without a rejected Origin or Fetch Metadata header.

## Next steps

- Add storage and a protected write endpoint with [models and controllers](models-and-controllers.md).
- Publish a database-backed page with [CMS and admin](cms-and-admin.md).
- Extract reusable presentation into a [component](components.md).
- Understand [routing and permission checks](Router.md) before exposing application data.
