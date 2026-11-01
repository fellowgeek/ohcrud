# Database reference

[Documentation](DOCUMENTATION.md) · [Models and controllers](models-and-controllers.md) · [Migrations](Migrations.md)

Source: [DB.php](../public/ohcrud/DB.php). `DB extends Core` and opens one PDO connection per instance using `__OHCRUD_DB_CONFIG__`. Supported driver names are `SQLITE` and `MYSQL`. Query results use `PDO::FETCH_OBJ`.

## Public methods

| Method | Result and behavior |
| --- | --- |
| `run($sql, $bind = [], $updateSuccess = true)` | Returns `$this`; result sets replace `$data` with row objects; non-result statements set an empty object |
| `create($table, $data = [])` | Returns `$this`; filters to schema columns, inserts, applies creation stamps |
| `read($table, $where = '', $bind = [], $fields = '*')` | Returns `$this`; selects fields, optionally adds WHERE |
| `update($table, $data, $where, $bind = [])` | Returns `$this`; filters columns and applies modification stamps |
| `delete($table, $where, $bind = [])` | Returns `$this`; physically deletes matching rows |
| `first()` | First row object or `false`, not a fluent return |
| `getPrimaryKeyColumn($table)` | First detected primary-key column or `false`; no composite-key abstraction |
| `details($table = '', $returnColumnDetails = false)` | Schema object keyed by table name, including row counts and optional columns; errors use Core state/output |
| `detectDataType(string $value, string $type): string` | UI-oriented heuristic, not a validator or schema constraint |

```php
$pages = new \app\models\mPages();
$page = $pages->read('Pages', 'URL = :URL', [':URL' => '/'])->first();
if ($pages->success === false) {
    // Handle the query failure using the model's errors.
} elseif ($page === false) {
    // Handle a missing page.
} else {
    $title = $page->TITLE;
}
```

`lastInsertId` is useful immediately after a successful insert. The implementation assigns PDO's last-insert value for other non-result statements too; do not use it as an affected-row count. `run(..., false)` avoids updating success on successful execution, but failures still mark an error. A later successful query can overwrite `$success`, while `$errors` retains earlier messages: check each operation promptly.

## SQL safety and filtering

Values are bound through PDO. Table names are checked for alphanumeric/underscore syntax, but `$where` and `$fields` are raw SQL. Never concatenate untrusted values, column names, order clauses, or limits into these fragments without validation and an allowlist.

`create()` and `update()` discard keys absent from the table schema. This is schema filtering, not a business-field allowlist. Explicitly select fields before calling them; passing a payload wholesale can expose status, owner, permission, or audit columns. A filtered empty write can still fail as invalid SQL. Raw `run()` bypasses field filtering and stamping.

## Audit fields

With `__OHCRUD_DB_STAMP__ == true`:

| Operation | Automatically assigned fields |
| --- | --- |
| Create | `CDATE`, `CUSER` |
| Update | `MDATE`, `MUSER` |

Dates use PHP's configured timezone. User IDs come from `$_SESSION['User']->ID`, or null without a session. The helper attempts to add missing audit columns during writes. Framework migration `002-audit-columns.php` provisions these for built-in tables; custom-table migrations should include them explicitly to avoid runtime DDL. Query-helper stamping is not an immutable audit history.

## Errors and portability

PDO exceptions are logged privately; callers receive `success = false`, `data = false`, an error, and HTTP status 500. `first() === false` can therefore mean either no row or query failure; inspect `success` first. Some invalid-table/connection paths call `output()` internally, so validate application input before invoking database helpers.

The layer does not normalize engine SQL, provide relationships, manage nested transactions, or apply migrations automatically in production requests. Match column casing, use driver branches where required, and test both backends when promising portability. The MySQL connection does not explicitly set a charset, and framework table definitions include legacy `utf8`; plan explicit migrations and connection changes if full Unicode storage is required.

See [transaction and relationship examples](models-and-controllers.md) and [production operations](deployment-and-testing.md).
