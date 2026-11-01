# Components and PHP views

[Documentation](DOCUMENTATION.md) · [CMS and admin](cms-and-admin.md) · [Themes](Themes.md)

Components are autoloaded PHP classes under `public/app/components/`. The CMS constructs each with the request and current path, calls `output($parameters)`, and collects its `content`, metadata, CSS, and JavaScript. Unlike a controller, a component's `output()` normally fills `content->html` without printing an HTTP response.

## Build a reusable greeting

Create `public/app/components/Greeting/Greeting.php`:

```php
<?php
namespace app\components\Greeting;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Render a reusable greeting through a local template.
class Greeting extends \app\components\Component {

    // Prepare escaped template data and component output.
    public function output($parameters = []) {
        $this->variables = ['name' => $parameters['name'] ?? 'developer'];
        $this->content->html = $this->loadView(
            'app/components/Greeting/views/GreetingView.phtml'
        );
    }
}
```

Create `public/app/components/Greeting/views/GreetingView.phtml`:

```php
<?php
// Prevent direct access to this template.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }
?>
<p>Hello, <?php print($this->escapeHTML($name)); ?>!</p>
```

Place this in a database page or theme:

```text
[[Greeting/Greeting|name=Ada]]
```

Match namespace and directory case exactly for case-sensitive deployments. The base constructor already supplies `$this->request`, `$this->path`, and an `mContent` instance. It also opens a database connection even when the component does not query data.

## Parameters and templates

The first marker segment is a component path relative to `app/components/`, without `.php`; slash or backslash separators are accepted. Subsequent `|key=value` segments form an associative array. Whitespace is trimmed, values remain strings, duplicate keys overwrite earlier values, and a bare key has an empty value. There is no escaping convention for a literal `|` inside a value. Commas do not separate parameters.

`loadView($fileName)` resolves a path relative to `public/`, verifies the real path is beneath `app/components/`, and requires a `.phtml` file. It returns the captured HTML string or `false`. `$variables` are extracted with `EXTR_SKIP`, and the template also has access to `$this`.

Escape ordinary text and attributes with `escapeHTML()`. For links, validate the scheme and then escape the attribute:

```php
<a href="<?php print($this->escapeHTML($this->safeURL($url))); ?>">Open</a>
```

`safeURL()` is not HTML escaping. Component HTML is trusted output and is inserted after Markdown purification; escape values at the rendering boundary. Use JSON encoding for JavaScript values rather than HTML escaping.

## Add component assets

After creating the corresponding owned asset files, register public paths in `output()`:

```php
$this->includeCSSFile('/app/components/Greeting/css/greeting.css', 20);
$this->includeJSFile('/app/components/Greeting/js/greeting.js', 20);
$this->includeMetaTags('description', 'A personalized welcome');
```

The CMS collects assets from all rendered components, de-duplicates by path, and sorts ascending priority. The first registration of a path wins its priority. Metadata is keyed by tag name, so later values replace earlier ones. Use local public paths when minification is enabled: the bundler reads files from disk.

## Data and authorization

A component inherits DB helpers and can query models or call application services. There is no endpoint-attribute check when the CMS instantiates a component. Enforce authorization before querying protected data:

```php
if (isset($_SESSION['User']) == false) {
    $this->content->html = '<p>Please sign in.</p>';
    return;
}
```

For owner-scoped records, bind the current user's ID in the query; login alone is insufficient. Components may be rendered multiple times, so keep rendering free from writes and avoid uncontrolled repeated queries. The CMS scans installed PHP component paths as its allowlist; this is filesystem containment, not a role-policy registry.

## Existing examples

| Component | Purpose |
| --- | --- |
| `Clock/Clock` | Local view and JavaScript asset example |
| `SEO` | Allowed metadata keys; use pipes between parameters |
| `CMS/Header/Header`, `CMS/Hero/Hero` | Page header and hero sections |
| `CMS/ContactCard/ContactCard`, `CMS/FeatureCard/FeatureCard` | Reusable information cards |
| `CMS/PostCard/PostCard`, `CMS/UseCase/UseCase`, `CMS/Figure/Figure` | Content presentation blocks |

Inspect each component's `output()` defaults and its view before relying on parameters. The SEO component emits `<meta name="…">` entries, including for keys such as `canonical`; it does not implement a canonical `<link>` element or Open Graph `property` attributes. Add those deliberately in a theme if required.

Source: [Component.php](../public/app/components/Component.php), [CMS component loading](../public/app/controllers/cCMS.php). Continue with [theme layouts](Themes.md).
