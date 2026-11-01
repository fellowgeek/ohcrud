# CMS and administration

[Documentation](DOCUMENTATION.md) · [Components](components.md) · [Themes](Themes.md) · [Admin API reference](Admin.md)

## Content resolution

The CMS is the default handler for paths outside the API map. It lowercases the normalized path and looks for a matching PHP file under `public/app/views/cms/` before querying `Pages.URL`. For example, `/about/` resolves to `app/views/cms/about.phtml` if present; otherwise it selects the database row whose URL is `/about/`.

File-backed views execute trusted application PHP and are not editable through the page-save API. They do not inherit a database page's permissions: add their own authorization when needed, as the built-in [security view](../public/app/views/cms/security.phtml) does. The root `/` comes from the database rather than an empty file path. Unknown pages render the 404 view; restricted database pages render the 403 view.

## Page schema

Source: [mPages.php](../public/app/models/mPages.php).

| Field | Meaning |
| --- | --- |
| `ID` | Generated primary key |
| `URL` | Unique page path; use lowercase and surrounding slashes, e.g. `/about/` |
| `TITLE` | Page title |
| `TEXT` | Markdown/source content |
| `GROUP` | Application grouping metadata; not a router role |
| `PERMISSIONS` | `-1` public, otherwise maximum admitted numeric user level |
| `THEME`, `LAYOUT` | Theme directory and layout basename |
| `STATUS` | `0` deleted, `1` published, `2` draft |
| `CDATE`, `MDATE`, `CUSER`, `MUSER` | Audit stamps when enabled/provisioned |

`mContent` is a rendering object, not a separate content table. Its `text`, `html`, `title`, `theme`, `layout`, `status`, `statusCode`, `type`, and asset fields carry content through the renderer. There is no configurable content-type builder, revision history, approval queue, or scheduled publishing engine. Use custom tables/models for structured domain content.

## Publish a page

1. Sign in at `/login/` with the administrator you provisioned.
2. Visit `/about/?action=edit`, including for a new path.
3. Enter a title and Markdown, choose a theme/layout, and select draft or published status.
4. Save, then visit `/about/` outside edit mode to check the published result.

A new, unsaved URL can retain HTTP 404 while showing the editor. Draft/deleted pages return 404 outside edit mode, including for administrators. Permission checks occur before status handling. The admin UI requires the authenticated user's permission value to be exactly integer `1`.

The editor uses `POST /ohcrud/pages/save/`. For a custom client on a signed-in CMS page:

```javascript
const response = await fetch('/ohcrud/pages/save/', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        CSRF: __CSRF__, URL: '/about/', TITLE: 'About',
        TEXT: '# About\n\nOur application is built with ohCRUD.',
        THEME: 'focus', LAYOUT: 'index', STATUS: 1
    })
});
const result = await response.json();
```

The handler requires nonempty URL, TITLE, and TEXT plus STATUS, purifies title/text, defaults omitted theme/layout, and saves by URL. Theme/layout names must contain only letters, digits, underscores, or hyphens. Invalid status values become draft. The endpoint does not accept permission/group changes as part of its saved field list; use controlled administration for those fields.

`restoreDeletePage` toggles published to deleted, and every other current status to published. This is a soft status change, not revision restoration. Physical deletion through generic database operations is a separate operation.

## Markdown, embeds, and dynamic content

Database text is transformed with Markdown Extra and then HTMLPurifier. Strike-through syntax is supported; permitted iframe sources are limited to the configured YouTube/Vimeo patterns in `cCMS`.

Embed another page with its path minus the surrounding slashes:

```text
{{shared/contact}}
```

Render a component with pipe-separated parameters:

```text
[[CMS/ContactCard/ContactCard|title=Support|contact=help@example.com|link=mailto:help@example.com]]
[[SEO|description=Our support page|robots=index,follow]]
```

Embedded pages retain permission/status checks. Recursive expansion is bounded by the renderer's counter (default maximum 7); avoid cyclic page/component references. Components execute on every request and must enforce their own data authorization. CMS editor access allows invoking installed components, so only install components appropriate for that trust level.

Only purified Markdown fragments may be shared in the CMS cache. Full rendered pages, embedded authorization decisions, component output, and session-specific JavaScript are rendered each request. See [Core caching](Core.md#cache) before adding application caches.

## Media handling

Source: [cFiles.php](../public/app/controllers/cFiles.php), [mFiles.php](../public/app/models/mFiles.php).

Upload through the editor/file manager or submit multipart form data to `/ohcrud/files/upload/`. It requires administrator permission, a top-level `CSRF` field, and the file field named `0`:

```javascript
const form = new FormData();
form.append('CSRF', __CSRF__);
form.append('0', document.querySelector('input[type=file]').files[0]);
const response = await fetch('/ohcrud/files/upload/', {method: 'POST', body: form});
const result = await response.json();
```

Let the browser set the multipart boundary. Successful `data` includes `ID`, `NAME`, `PATH`, `SIZE`, `TYPE`, dimensions, and status metadata. Files receive random basenames beneath `public/global/files/`; the database stores their public path.

| Limit | Behavior |
| --- | --- |
| File size | At most 16 MiB at the application layer; PHP/proxy limits may be lower |
| Raster images | JPEG, PNG, GIF, WebP; valid dimensions and at most 20 million source pixels |
| Other allowed extensions | CSV, TXT, PDF, XML, XLSX, JSON, ZIP, MP3, with MIME checks |
| SVG | New SVG uploads are rejected |
| Derivative dimensions | Width 1–3840, height 1–2160, quality 1–100; computed dimensions are also bounded |

Example image request after uploading a raster file:

```text
/ohcrud/files/image/?filename=<uploaded-basename>.jpg&w=640&q=80
```

Omit one dimension to preserve aspect ratio. Supplying both sets the output dimensions directly. Quality affects JPEG/WebP encoding; derivatives are streamed instead of written to an unbounded disk cache. `rand` disables normal response caching. The supplied Nginx rules redirect query-bearing raster asset URLs to this endpoint.

Uploads are publicly served assets. A database `STATUS` or a restricted page containing a link does not protect the underlying file. Build a separately authorized download path and private storage for confidential documents. Preserve Nginx's no-execution and attachment rules when deploying.

## Admin capabilities

| URL/query | Purpose |
| --- | --- |
| `/?action=edit` | Edit the current database page |
| `/?action=tables` | Schema-driven table browser and CRUD forms |
| `/?action=users` | User-table specialization |
| `/?action=files` | Upload and browse media |
| `/?action=logs` | Browse and clear application logs |
| `/?action=server` | Server information view |
| `/?action=sql` | SQL interface; execution disabled by default |
| `/security/` | Signed-in user's own authenticator management |

The table UI introspects columns and sample values to choose controls/icons. This is runtime CRUD scaffolding, not generated controllers or models. Generic operations exclude `AuthState`; user writes use dedicated endpoints, and enrolled TOTP secrets are not exposed. Other application tables remain broadly accessible to administrators. Do not treat this interface as row-level authorization for ordinary users.

## Customize the admin and dashboard

The admin shell lives in `public/themes/admin/`, views in `public/app/views/admin/`, and owned interaction code in `public/themes/admin/assets/js/admin.js`. Edit owned files and preserve their endpoint/CSRF contracts. There is no dashboard-widget registry. For an application dashboard, create a protected file view plus components, or extend the controller's action allowlist, action/layout mapping, view, and navigation together. Adding an arbitrary `?action=` does not load a new view automatically.

See [Admin](Admin.md) for payload contracts and [Users](Users.md) for account and permission behavior.
