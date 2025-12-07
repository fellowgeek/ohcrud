# Database Object

The `DB` object is in charge of all the CRUD (*C*reate *R*ead *U*pdate *D*elete) work. This class inherits from `Core`, and comes with all the properties and methods that `Core` has.

## Properties

In addition to the `Core` object properties, the `DB` object has:

| Property | Type | Description |
| --- | --- | --- |
| `lastInsertId` | integer | Stores the most recently generated auto-increment ID from a successful INSERT query. |
| `config` | array | Configuration settings for the database. |
| `db` | PDO | The PDO database connection instance. |
| `SQL` | string | Stores the last SQL query executed. |

## Methods

Same as `Core` object plus the following:

| Method | Description | Return Value |
| --- | --- | --- |
| `run($sql, $bind=array(), ...)` | Executes any SQL query against the database. | `DB` Object |
| `create($table, $data=array())` | Inserts a new record into the database. | `DB` Object |
| `read($table, $where="", ...)` | Reads records from the database. | `DB` Object |
| `update($table, $data, $where, ...)` | Updates records in the database. | `DB` Object |
| `delete($table, $where, ...)` | Deletes records from the database. | `DB` Object |
| `first()` | Returns the first element of the `data` property. This method will terminate a method chain. | Object |
| `getPrimaryKeyColumn($table)` | Returns the primary key column name for a given table. | string / null |
| `details($table = '', $returnColumnDetails = false)` | Returns the details of all tables or a specific table. If `$returnColumnDetails` is true, it also returns column details, including auto-detected data types for each column. | \stdClass / void |
