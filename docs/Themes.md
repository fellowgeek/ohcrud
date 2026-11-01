# Themes and layouts

[Documentation](DOCUMENTATION.md) · [Components](components.md) · [CMS and admin](cms-and-admin.md)

A theme is a directory under `public/themes/`; a layout is an `.html` file within it. The sample defaults select `focus/index.html`. Pages store theme/layout basenames, and missing or invalid selections fall back to configured defaults. These files are included by PHP, so they are trusted application code even though their extension is HTML.

## Create a theme

Create `public/themes/mytheme/index.html`:

```html
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{CMS:TITLE}}</title>
    {{CMS:META}}
    {{CMS:STYLESHEET}}
</head>
<body>
    <header><a href="/">Home</a></header>
    <main>{{CMS:CONTENT}}</main>
    <footer>{{CMS:OHCRUD}}</footer>
    {{CMS:JAVASCRIPT}}
</body>
</html>
```

Select `mytheme` and `index` when saving a page. To change defaults for new content, edit `__OHCRUD_CMS_DEFAULT_THEME__` and `__OHCRUD_CMS_DEFAULT_LAYOUT__`. Existing pages retain their stored selections. Theme discovery excludes the configured admin theme and scans `themes/*/*.html`; run the application with the public directory as its expected web working directory.

## Placeholders

| Marker | Output |
| --- | --- |
| `{{CMS:CONTENT}}` | Rendered page HTML and per-request admin controls |
| `{{CMS:CONTENT-TEXT}}` | HTML-escaped source text, suitable for editor text content |
| `{{CMS:TITLE}}` | HTML-escaped page title |
| `{{CMS:META}}` | Collected metadata tags |
| `{{CMS:STYLESHEET}}` | Collected CSS links or bundled stylesheet |
| `{{CMS:JAVASCRIPT}}` | Per-request constants, including `__CSRF__`, then script assets |
| `{{CMS:APP}}` | Configured application name; trusted configuration, not automatically escaped |
| `{{CMS:SITE}}`, `{{CMS:DOMAIN}}`, `{{CMS:SUB_DOMAIN}}` | Escaped host-derived values |
| `{{CMS:PATH}}` | Filtered CMS path |
| `{{CMS:VERSION}}` | Core version string |
| `{{CMS:OHCRUD}}` | Framework footer and login/logout link |

Admin rendering also replaces `{{CMS:THEME}}`, `{{CMS:LAYOUT}}`, `{{CMS:STATUS}}`, and `{{CMS-IS-DELETED}}`. The internal `UNCACHABLE-HTML` and `UNCACHABLE-JS` markers are filled late; preserve the main content and JavaScript placeholders instead of caching their session-specific results.

Placeholder substitution is textual, not context-aware templating. Keep values in their intended HTML context; do not insert title/source markers directly into executable JavaScript. Themes support the same page embeds and component markers as CMS content.

## Asset paths and ordering

Theme-relative asset references use double-quoted attributes:

```html
<link rel="stylesheet" href="assets/css/styles.css">
<img src="assets/img/logo.png" alt="Application logo">
```

The renderer rewrites selected `href`/`src` attributes on script/link/use/img tags to the current theme directory. It is a regex-based transformation, not a general URL resolver: even a leading slash on these asset attributes may be prefixed. Ordinary anchor links are not rewritten. For shared global/component assets, register them through `includeCSSFile()` and `includeJSFile()` instead. Explicit HTTP(S) asset URLs are excluded from the theme path rewrite.

Registered assets are de-duplicated and ordered by ascending priority. Global assets use priority 1, extracted theme assets normally use 10, and component defaults use 100.

## Minification and caching

Enable `__OHCRUD_CMS_MINIFY_CSS__` and/or `__OHCRUD_CMS_MINIFY_JS__` only when the collected assets resolve to readable local files and `public/global/minified/` is writable. Bundles use source order, file metadata, and `__OHCRUD_CMS_ASSET_VERSION__` to derive a filename. They are written atomically. Change the release identifier or clear generated bundles when deploying content with preserved file metadata.

`__OHCRUD_CMS_LAZY_LOAD_IMAGES__` adds `loading="lazy"` where an image has no loading attribute; existing eager/lazy settings remain. Full CMS HTML is rendered per request. See [Core caching](Core.md#cache) and [deployment](deployment-and-testing.md).

Source: [cCMS.php](../public/app/controllers/cCMS.php), [focus theme](../public/themes/focus/index.html).
