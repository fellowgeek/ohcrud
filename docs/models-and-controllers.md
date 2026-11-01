# Models and controllers

[Documentation](DOCUMENTATION.md) · [Routing](Router.md) · [DB reference](DB.md)

This guide builds a small Notes feature. Its API has an administrator-only write method and a published-note read method. Models contain reusable queries; controllers own request validation, authorization, and response generation.

## Create the schema

Add `private/migrations/003-notes.php` (choose the next unused numeric version in your application):

```php
<?php
// Prevent direct access to this migration.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Create portable application storage with audit columns already provisioned.
return function ($db, $driver) {
    $idType = $driver === 'sqlite'
        ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
        : 'INTEGER UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $db->exec('CREATE TABLE IF NOT EXISTS Notes (
        ID ' . $idType . ',
        TITLE VARCHAR(200) NOT NULL,
        STATUS INTEGER NOT NULL DEFAULT 1,
        CDATE DATETIME NULL,
        MDATE DATETIME NULL,
        CUSER INTEGER NULL,
        MUSER INTEGER NULL
    )');
};
```

Run `php private/migrate.php` in the configured environment, or the [Compose equivalent](Migrations.md). Keep applied migrations immutable. SQL/PHP migration execution differs between SQLite and MySQL; read the [transaction and recovery rules](Migrations.md).

## Add a model

Create `public/app/models/mNotes.php`:

```php
<?php
namespace app\models;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Keep note queries reusable across controllers and components.
class mNotes extends \ohCRUD\DB {

    // Return a published note, or false when no row matches.
    public function findPublished($id) {
        return $this->read('Notes', 'ID = :ID AND STATUS = :STATUS', [
            ':ID' => $id,
            ':STATUS' => self::ACTIVE,
        ], 'ID, TITLE, STATUS')->first();
    }
}
```

The inherited constructor opens the configured PDO connection. No table-name inference or model registration is required. Do not give query helpers `Endpoint` attributes unless they are intentionally exposed as validated request handlers.

## Controller validation and writes

Create `public/app/controllers/cNotes.php`:

```php
<?php
namespace app\controllers;

// Prevent direct access to this class.
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Expose the note feature through explicit endpoint contracts.
class cNotes extends \app\models\mNotes {

    // Read only published notes from this public endpoint.
    #[\ohCRUD\Endpoint(permission: __OHCRUD_PERMISSION_ALL__, methods: ['GET'])]
    public function show($request) {
        $this->setOutputType(self::OUTPUT_JSON);
        $id = filter_var($request->id ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            return $this->error('A positive note ID is required.')->output();
        }

        $note = $this->findPublished($id);
        if ($this->success === false) {
            return $this->output();
        }
        if ($note === false) {
            return $this->error('Note not found.', 404)->output();
        }
        $this->data = $note;
        return $this->output();
    }

    // Create a note only after authorization and input validation.
    #[\ohCRUD\Endpoint(permission: 1, methods: ['POST'])]
    public function add($request) {
        $this->setOutputType(self::OUTPUT_JSON);
        if ($this->checkCSRF($request->payload->CSRF ?? '') === false) {
            return $this->error('Missing or invalid CSRF token.')->output();
        }

        $title = $request->payload->TITLE ?? null;
        if (is_string($title) == false || trim($title) === '' || strlen($title) > 200) {
            return $this->error('TITLE must contain 1–200 bytes of text.')->output();
        }

        // Select writable fields explicitly; do not pass the entire payload.
        $this->create('Notes', [
            'TITLE' => trim($title),
            'STATUS' => self::ACTIVE,
        ]);
        if ($this->success === false) {
            return $this->output();
        }
        $this->outputStatusCode = 201;
        $this->data = ['ID' => $this->lastInsertId];
        return $this->output();
    }
}
```

Add `'/notes/' => 'app\controllers\cNotes',` inside the route map. From a signed-in administrator's CMS page, send:

```javascript
const response = await fetch('/notes/add/', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({CSRF: __CSRF__, TITLE: 'First note'})
});
const result = await response.json();
if (response.ok === false || result.success === false) {
    console.error(result.errors);
} else {
    console.log(result.data.ID);
}
```

`__CSRF__` is emitted by the theme's JavaScript placeholder and belongs to the current session. Fetch is same-origin here, so the browser sends its session cookie. Then request `/notes/show/?id=<returned ID>`.

There is no validation DSL or middleware pipeline. Reuse explicit helper methods for repeated checks. Endpoint permission checks are coarse-grained: add ownership/tenant predicates to queries where needed. The public read example intentionally exposes every published note.

## Update and delete

Within a validated, authorized handler, select writable fields and bind the record identifier:

```php
$this->update('Notes', ['TITLE' => $title], 'ID = :ID', [':ID' => $id]);
```

```php
$this->delete('Notes', 'ID = :ID', [':ID' => $id]);
```

These helpers do not verify that a row existed or that the user owns it. Use an ownership-aware read/update predicate and define missing-row behavior. `success` means the SQL executed, not that any rows changed. Add CSRF checks to every session-authenticated mutation. For soft deletion, explicitly update `STATUS`; the generic `delete()` is a physical SQL DELETE.

## Relationships and joins

Relationships are represented by ordinary identifier columns and SQL. There are no `belongsTo()` or eager-loading APIs. For the audit user's display name:

```php
$this->run('SELECT n.ID, n.TITLE, u.NAME AS AUTHOR
    FROM Notes n
    LEFT JOIN Users u ON u.ID = n.CUSER
    WHERE n.STATUS = :STATUS
    ORDER BY n.ID DESC
    LIMIT 20', [':STATUS' => self::ACTIVE]);
$notes = $this->data;
```

For business relationships, create the foreign-key column and index in a migration, decide nullability/delete policy, and verify enforcement in both engines. SQLite foreign-key enforcement is not explicitly enabled by the current DB constructor. Do not assume it is active. Each query replaces `$data`; save results you need before issuing another query.

For dynamic sorting, map a client option to an allowlisted SQL identifier. Bound parameters represent values, not identifiers or SQL fragments. For pagination, validate and cap integer limits/offsets before inserting them into a query.

## Transactions

Use the exposed PDO connection. DB helpers catch PDO failures and record state, so check each operation before committing:

```php
$this->db->beginTransaction();
try {
    $this->create('Notes', ['TITLE' => $title, 'STATUS' => self::ACTIVE]);
    if ($this->success === false) {
        throw new \RuntimeException('Note creation failed.');
    }
    // Execute any additional related writes here and check each result.
    $this->db->commit();
} catch (\Throwable $exception) {
    if ($this->db->inTransaction() == true) {
        $this->db->rollBack();
    }
    return $this->error('Unable to complete the operation.', 500)->output();
}
```

Provision audit columns before transactional writes: automatic column additions can trigger DDL, especially problematic with MySQL implicit commits. Separate model instances create separate connections; a transaction on one does not cover writes on another. For affected-row counts or operations needing exceptions, use a prepared statement directly and translate failures into the controller response.

## Verify the feature

Lint both classes and the migration. In a disposable environment, migrate, create a note as an administrator, read it anonymously, and test invalid IDs/titles, missing CSRF, anonymous writes, insufficient permissions, and wrong HTTP verbs. Confirm database failures are not reported as missing rows. See [validation](deployment-and-testing.md#validation) and [Core response contracts](Core.md).
