# ohCRUD coding style

This guide captures the conventions used across the framework, controllers,
models, components, and owned browser assets. Follow it for new code and code
being modified. Existing files contain historical variations; preserve nearby
formatting in small edits instead of reformatting entire files.

## 1. Explicit conditions

Spell out boolean checks. This is a deliberate project convention, including
checks of predicates such as `isset()`, `empty()`, and `file_exists()`.

```php
// Stop when caching is disabled.
if (__OHCRUD_CACHE_ENABLED__ == false) {
    return false;
}

// Only read a cache entry when the file exists.
if (file_exists($path) == true) {
    $data = file_get_contents($path);
}

if (isset($request->payload) == false || empty($request->payload->TITLE) == true) {
    $this->error('Missing or incomplete data.');
}
```

- Prefer `if ($enabled == true)` over `if ($enabled)` and
  `if ($enabled == false)` over `if (!$enabled)` for boolean flags.
- Keep `&&` and `||` conditions readable; compare each boolean operand explicitly.
- Use direct value comparisons for values: `$count > 0`, `$type == 'HTML'`, etc.
  Do not wrap these in another comparison to `true`.
- Use `===` / `!==` when distinguishing types or sentinel values. For example,
  `stripos($text, 'json') !== false` must distinguish position `0` from failure.
  Likewise, preserve strict checks of database results and success state.
- Do not change comparison operators as formatting cleanup. Loose and strict
  comparisons, `isset()`, `empty()`, and null checks have different semantics;
  choose the check that implements the intended behavior.
- Use lowercase `true`, `false`, and `null` in new code. Existing uppercase `NULL`
  is a legacy variation, not a reason to rewrite surrounding code.

## 2. Layout and whitespace

- Indent with four spaces. Keep braces on the declaration/condition line for
  classes, methods, loops, `if`, `switch`, and `try` blocks.
- Put one space between control keywords and `(`, none between a function name
  and `(`, and no padding just inside parentheses: `if ($a == true) {`.
- Put spaces around assignments, comparisons, `=>`, concatenation (`.`), and
  other binary operators. Put a space after commas.
- Prefer `} else {`, `} elseif (...) {`, and `} catch (...) {` in new blocks.
  Some existing code places `else` on a separate line; follow nearby formatting.
- Use braces for new control-flow blocks. Existing one-line guards and unbraced
  checks are legacy variants; preserve them when unrelated to the change.
- Separate logical steps and methods with blank lines. A blank line just inside
  a class or a longer method is common; do not add repeated empty lines.
- Wrap long calls and arrays across lines, with one argument or entry per line
  when it improves readability. No repository-wide numeric line limit is defined.
- Avoid trailing whitespace and end new files with a newline.

## 3. Comments are part of the style

Use plain-English `//` comments above methods and meaningful groups of operations:
validation, configuration, query execution, rendering, cache invalidation, etc.
Describe the operation and any reason or constraint the next reader needs.

```php
// Set the output type for the response.
public function setOutputType($outputType) {
    $this->outputType = $outputType;
    return $this;
}
```

Keep comments accurate when editing code. Do not strip explanatory comments as
redundant simply because the code is readable. Avoid narrating every assignment
or leaving stale commented-out implementations. Use `/* ... */` for longer usage
examples, as in components and configuration; PHPDoc is useful when a contract
needs it, but is not required boilerplate for every method. Section comments can
help organize long workflows, as in `Router::route()`.

## 4. Names and file structure

| Element | Convention and examples |
| --- | --- |
| Framework classes | `Core`, `DB`, `Router`, `Users` in namespace `ohCRUD` |
| Controllers | `cPages`, `cUsers`, `cExample` in `app\controllers` |
| Models | `mPages`, `mContent`, `mFiles` in `app\models` |
| Components | Descriptive class names such as `Hero` and `PostCard` |
| Methods, properties, locals | camelCase: `setOutputType`, `$statusCode`, `$cacheKey` |
| Class constants | Uppercase: `OUTPUT_JSON`, `PUBLISHED`, `TYPE_FILE` |
| Configuration constants | Existing `__NAME__` form, usually `__OHCRUD_...__` |
| Tables and columns | Preserve `Pages`, `Users`, `Files`; `ID`, `TITLE`, `STATUS` |
| Payload fields | Preserve the API's uppercase keys, e.g. `$request->payload->URL` |

Use descriptive names and preserve established acronyms such as `CSRF`, `SQL`,
and `TOTP`. Do not rename existing symbols to enforce a general naming rule.
Match class filenames and namespace paths for Composer autoloading, paying
attention to case on case-sensitive filesystems. Existing component namespace
casing varies; do not copy a mismatch into a new component.

PHP class files begin with `<?php`, their namespace, optional `use` imports, the
direct-access guard, and a short class-purpose comment. Follow this shape:

```php
<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Controller cExample - sample public and protected endpoints.
class cExample extends \ohCRUD\DB {

    // Define permissions for the controller.
    public $permissions = [
        'object' => __OHCRUD_PERMISSION_ALL__,
        'whoami' => __OHCRUD_PERMISSION_ALL__,
    ];
}
```

Fully qualified framework/application class references are common; `use` imports
are also used, especially for external dependencies. Match the file. Keep members
and constructors organized like neighboring classes. Existing APIs largely use
untyped parameters and properties: do not add `strict_types`, type declarations,
constructor promotion, `readonly`, or visibility changes as style-only cleanup.
Preserve override signatures and caller compatibility.

## 5. Strings, arrays, and calls

- Prefer single quotes for simple literal strings; use double quotes for
  interpolation, escapes, or readable embedded SQL/HTML. Existing quote styles
  are mixed: do not churn unaffected strings.
- Use spaced concatenation: `'Location: ' . $url`.
- Prefer `[]` for new arrays. Preserve `array()` in existing signatures and nearby
  legacy code unless changing it is part of the task.
- Use readable multiline associative arrays for permissions, bindings, and data.
  Follow local trailing-comma usage; trailing commas are suitable in new multiline
  arrays. Do not align every `=>` with tabs or reflow whole arrays unnecessarily.
- Use `??` for missing/null defaults where appropriate; it is not interchangeable
  with an emptiness check. Retain explicit casts where values need normalization.
- Keep fluent calls when they express the existing API, such as
  `$this->read(...)->first()`, and preserve `return $this` contracts.

```php
// Read the requested page using a bound identifier.
$page = $this->read(
    'Pages',
    'ID = :ID',
    [
        ':ID' => $id,
    ]
)->first();
```

## 6. Framework and database idioms

Controllers receive `$request`; API JSON lives under `$request->payload`.
Follow the existing sequence of selecting output type, validating input and CSRF,
performing work, setting response data, and calling `output()`. Register route
and permission entries when adding endpoints. Follow the relevant controller's
error/early-return pattern and keep expected HTTP status codes intact.

Reuse `Core` and `DB` helpers rather than inventing a second response or query
layer. SQL uses uppercase keywords, existing identifier casing, and named value
bindings such as `:ID`. Format longer SQL over multiple lines. Bind values instead
of interpolating request data; dynamic identifiers require validation. Preserve
SQLite/MySQL branches, object-style result access, and framework audit columns
(`CDATE`, `MDATE`, `CUSER`, `MUSER`). Existing unsafe patterns are not style rules.

## 7. Templates and browser assets

Keep `.phtml` views and the existing theme/component layout. Views commonly use
`<?php print($value); ?>` and PHP blocks around conditional HTML. Keep business
logic in the relevant PHP class and use context-appropriate escaping for untrusted
values; existing raw output does not establish a rule to skip escaping. Preserve
intentional rich HTML rendering and the associated sanitization contract.

Owned JavaScript uses comments, semicolons, camelCase names, and same-line braces.
Use explicit checks for boolean flags, while preserving type-sensitive JavaScript
semantics and local comparison conventions. Existing files mix `var`, `let`, and
`const`; avoid broad rewrites or introducing a build system for a small change.
Preserve established globals and helpers such as `__CSRF__`, `httpRequest()`,
`debugLog()`, and Framework7/Dom7 integration where used.

CSS generally uses four-space indentation, one declaration per line, and a space
after the colon. Match the local HTML/CSS style and existing class names. Edit
owned source assets, not bundled libraries or generated minified output.

## Reference files

- `public/ohcrud/Core.php`: explicit conditions, method comments, output helpers.
- `public/ohcrud/Router.php`: commented workflow, routing, permission checks.
- `public/app/controllers/cExample.php`: controller structure and multiline calls.
- `public/app/controllers/cPages.php`: validation, SQL bindings, response flow.
- `public/app/models/mContent.php`: properties, constants, descriptive comments.
- `public/app/components/Clock/Clock.php`: component usage notes and asset loading.
- `public/global/js/global.js`: owned browser helpers and comments.

These are style references, not guarantees that every legacy line is a pattern
to reproduce. Correctness comes first; keep behavioral fixes explicit and scoped.
