# Documentation

[Project home](../README.md)

These guides describe the implementation in this repository. Examples use the default `/ohcrud` API prefix and commands run from the repository root unless stated otherwise. Configuration lives in `public/settings.php`; there is no bundled `.env` loader.

## Learning path

1. [Getting started](getting-started.md): prepare PHP, configure storage, migrate, and sign in.
2. [Five-minute quickstart](quickstart.md): build an endpoint and a page.
3. [Architecture](architecture.md) and [routing](Router.md): trace requests and declare access.
4. [Models and controllers](models-and-controllers.md): implement application data and behavior.
5. [CMS and admin](cms-and-admin.md): publish content and manage records.
6. [Components](components.md) and [themes](Themes.md): build reusable interfaces.
7. [Authentication](Users.md) and [deployment and testing](deployment-and-testing.md): secure and operate the application.

## Reference shelf

| Reference | Scope |
| --- | --- |
| [Core](Core.md) | Response state, errors, sessions, cache, logs, CLI helpers |
| [DB](DB.md) | PDO CRUD contracts and schema inspection |
| [Router](Router.md) | Matching, attributes, request parsing, CORS, CLI |
| [Users](Users.md) | Authentication and factor management |
| [Migrations](Migrations.md) | Schema provisioning and immutable migration history |
| [Admin](Admin.md) | Built-in administrative API payloads |
| [Themes](Themes.md) | Rendering placeholders and asset rules |
| [Style guide](STYLE_GUIDE.md) | PHP, templates, and owned browser code conventions |

## Repository map

| Path | Responsibility |
| --- | --- |
| `public/index.php` | HTTP/CLI bootstrap, sessions, and router |
| `public/settings.sample.php`, `public/routes.php` | Configuration template and route policy |
| `public/ohcrud/` | Core, DB, Router, Users, attributes, and migration runner |
| `public/app/models/` | Page/file schema and content transfer object |
| `public/app/controllers/` | API handlers and CMS rendering |
| `public/app/views/` | CMS and admin PHP templates |
| `public/app/components/` | Reusable components, views, and local assets |
| `public/themes/`, `public/global/` | Theme layouts and browser assets |
| `private/migrate.php`, `private/migrations/` | CLI provisioning and schema updates |
| `private/create-admin.php` | Explicit administrator provisioning |
| `private/tests/`, `private/hurl/` | Isolated regression runners and HTTP suites |
| `private/nginx.conf`, `private/PHP.Dockerfile` | Development server configuration |

## Documentation architecture and maintenance

Task-oriented guides explain complete workflows; the existing capitalized reference filenames retain stable entry points. The README is the front door and this index is the navigation hub. Each guide links to its prerequisites, next steps, and relevant source.

When changing behavior, update the owning guide and its examples together. Verify signatures against source, distinguish fluent returns from data returns, and describe unsupported features explicitly. Do not copy local credentials or generated data into documentation. Validate relative links, PHP examples, and whitespace as described in [deployment and testing](deployment-and-testing.md#validation).
