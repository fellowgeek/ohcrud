# Working on ohCRUD

ohCRUD is a lightweight PHP framework with routing, CRUD, authentication, a CMS,
and reusable components. Preserve its straightforward, commented programming style.
Read [docs/STYLE_GUIDE.md](docs/STYLE_GUIDE.md) before changing code; it is the
repository's coding-style reference and applies to new and modified code.

## Project map

| Location | Responsibility |
| --- | --- |
| `public/index.php` | Web/CLI bootstrap, session setup, router creation |
| `public/ohcrud/` | Framework: `Core`, `DB`, `Router`, `Users` |
| `public/app/controllers/` | Request handlers, named `cExample`, `cPages`, etc. |
| `public/app/models/` | Models, named `mPages`, `mContent`, etc. |
| `public/app/components/` | Components and their local views/assets |
| `public/app/views/` | PHP templates (`.phtml`) |
| `public/themes/`, `public/global/` | Theme templates and browser assets |
| `public/routes.php` | Endpoint mapping and request access policy |
| `public/settings.sample.php` | Tracked configuration example |
| `public/composer.json` | Dependencies and autoloading: `app\`, `ohCRUD\` |
| `private/` | Server/container configuration and Hurl integration tests |
| `docs/` | Framework and API documentation |

## Required coding conventions

- Write explicit boolean conditions: `if ($enabled == true) {` and
  `if (isset($value) == false) {`. Do not shorten these to truthiness or `!` checks.
- Preserve `===` / `!==` where types or sentinel return values matter. Explicit
  comparisons do not mean mechanically converting every condition to `== true`.
- Use four spaces, same-line opening braces, and readable spacing. The dominant
  PHP form is `if ($enabled == true) {`, without padding inside parentheses.
- Keep explanatory `//` comments above methods and meaningful processing steps.
  Update comments when behavior changes; do not remove them just to shorten code.
- Use camelCase methods/variables, `c`/`m` class prefixes, and existing uppercase
  database fields and configuration constants. Preserve public names and casing.
- Follow the surrounding file for legacy variations. Do not impose PSR-12,
  run broad formatters, or modernize unrelated code as part of another change.

## Implementation expectations

- Read the affected implementation and its callers before editing. Existing docs
  can lag behind code; verify signatures, paths, and return behavior in source.
- Keep changes focused. Reuse framework helpers and the established inheritance
  structure rather than introducing a new abstraction or dependency by default.
- Preserve the `OHCRUD` direct-access guards, route mappings, controller permission
  maps, CSRF checks, and output conventions when extending an endpoint.
- Use `$this->data`, `$this->success`, `$this->errors`, `setOutputType()`, `error()`,
  and `output()` consistently with the relevant handler. Preserve fluent returns
  and distinguish data-returning methods from methods returning `$this`.
- Use database helpers and bound SQL values. Keep SQLite and MySQL behavior in
  mind for database changes; preserve schema names and audit fields.
- Keep PHP-only files free of closing `?>` tags. Preserve the existing template
  and component rendering mechanisms for `.phtml` and theme files.
- Do not edit dependencies, bundled third-party/minified assets, or generated
  files to implement application changes. Edit the corresponding owned source.
- Keep local settings, credentials, certificates, logs, uploads, and database
  contents out of commits. Document configuration changes in the sample settings.

## Validation

- Run `git diff --check` and inspect the final diff for unrelated changes.
- Lint each changed PHP or `.phtml` file with `php -l <file>`. If using the running
  Compose PHP service, use `docker compose exec -T php php -l /app/<path>`;
  `/app` corresponds to the repository's `public/` directory.
- Composer commands belong in `public/`, for example
  `composer --working-dir=public validate` for manifest changes and
  `composer --working-dir=public install` when dependencies are needed.
- The existing integration suite is `private/hurl/*.hurl`; no PHPUnit suite or
  configured PHP formatter is currently present. Run relevant behavioral checks
  for logic changes, including failure paths and permissions where applicable.
- Hurl tests require a configured HTTPS host, seeded database, default test user,
  and upload fixture. They create, update, upload, and delete data: use a disposable
  test environment. From `private/hurl/`, run
  `hurl --test --variable host=<test-host> *.hurl` after checking prerequisites.
  `test.sh` hardcodes `ohcrud.loc`; the Compose configuration uses `ohcrud.test`.
  CSRF failure coverage requires debug mode disabled.
- README documentation advertises PHP >= 8.0; the Dockerfile currently uses PHP
  8.5. Do not silently raise language requirements; check dependency constraints
  and the intended runtime when introducing newer PHP syntax.
- Report what changed, which checks ran, and any checks that could not run.
  Documentation-only edits do not require starting services or changing data.
