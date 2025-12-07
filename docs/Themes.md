# Theming System

ohCRUD features a flexible theming system that allows you to control the look and feel of your application.

## Directory Structure

Themes are located in the `public/themes/` directory. Each subdirectory inside `public/themes/` represents a theme.

```
public/
└── themes/
    ├── admin/      # The default admin theme
    │   ├── edit.html
    │   ├── login.html
    │   └── ...
    └── focus/      # An example public-facing theme
        ├── default.html
        └── home.html
```

Each theme directory contains one or more layout files (typically `.html` files).

## How it Works

1.  **Theme and Layout Selection**: For any given page requested through the CMS, the theme and layout are determined by the `THEME` and `LAYOUT` values stored for that page in the `Pages` database table. If a page does not have a specific theme or layout defined, the defaults specified by the `__OHCRUD_CMS_DEFAULT_THEME__` and `__OHCRUD_CMS_DEFAULT_LAYOUT__` constants are used.

2.  **Admin Theme**: The admin panel uses its own theme and layout, defined by `__OHCRUD_CMS_ADMIN_THEME__` and `__OHCRUD_CMS_ADMIN_LAYOUT__`.

3.  **Rendering Process**: The `cCMS` controller handles the rendering. The `processTheme()` method is responsible for:
    *   Loading the appropriate theme and layout file.
    *   Replacing placeholders within the layout file with dynamic content.
    *   Processing any components or embedded content within the theme file itself.
    *   Managing CSS and JavaScript assets.

## Layout Files and Placeholders

Layout files are simple HTML files that contain placeholders. These placeholders are replaced with actual content during the rendering process.

Here are some of the most common placeholders:

| Placeholder | Description |
| --- | --- |
| `{{CMS:CONTENT}}` | The main content of the page. |
| `{{CMS:CONTENT-TEXT}}` | The raw, un-processed text content of the page. |
| `{{CMS:TITLE}}` | The title of the page. |
| `{{CMS:META}}` | Meta tags for the page. |
| `{{CMS:STYLESHEET}}` | CSS `<link>` tags. |
| `{{CMS:JAVASCRIPT}}` | JavaScript `<script>` tags. |
| `{{CMS:OHCRUD}}` | The ohCRUD footer. |
| `{{CMS:APP}}` | The application name. |
| `{{CMS:SITE}}` | The site name. |
| `{{CMS:PATH}}` | The current URL path. |

### Example Layout (`default.html`)

```html
<!DOCTYPE html>
<html>
<head>
    <title>{{CMS:TITLE}} - {{CMS:APP}}</title>
    {{CMS:META}}
    {{CMS:STYLESHEET}}
</head>
<body>
    <header>
        <h1>{{CMS:TITLE}}</h1>
    </header>
    <main>
        {{CMS:CONTENT}}
    </main>
    <footer>
        {{CMS:OHCRUD}}
    </footer>
    {{CMS:JAVASCRIPT}}
</body>
</html>
```

## Asset Management

The theming system automatically handles paths for your assets (CSS, JS, images). When you use a relative path in an `href` or `src` attribute within your layout file, ohCRUD will prepend the path to the current theme directory.

For example, if you are using the `focus` theme and you have this in your layout:

```html
<img src="assets/img/logo.png">
```

It will be rendered as:

```html
<img src="/themes/focus/assets/img/logo.png">
```

The system also supports optional minification for CSS and JS files, which can be enabled in your `settings.php` file with `__OHCRUD_CMS_MINIFY_CSS__` and `__OHCRUD_CMS_MINIFY_JS__`.
