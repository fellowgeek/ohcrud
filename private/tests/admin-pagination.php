<?php
// Exercise pagination against disposable rows and logs, including empty and deep pages.
$GLOBALS['OHCRUD'] = true;
define('__OHCRUD_PERMISSION_ALL__', -1);
define('__OHCRUD_ADMIN_ENABLE_SQL_EXECUTION__', true);
$originalSettings = file_get_contents(getenv('OHCRUD_TEST_ROOT') . '/settings.php');
file_put_contents(getenv('OHCRUD_TEST_ROOT') . '/settings.php', str_replace("define('__OHCRUD_ADMIN_ENABLE_SQL_EXECUTION__', false);", '', $originalSettings));
try {
    require __DIR__ . '/autoload.php';
} finally {
    file_put_contents(getenv('OHCRUD_TEST_ROOT') . '/settings.php', $originalSettings);
}
class PaginationAdmin extends \app\controllers\cAdmin {
    // Inspect results directly instead of sending response headers in this fixture.
    public function output() { return $this; }
}
$_SESSION['CSRF'] = 'pagination-fixture';
$admin = new PaginationAdmin;
$admin->db->exec('CREATE TABLE PaginationFixture (ID INTEGER PRIMARY KEY, VALUE TEXT)');
$insert = $admin->db->prepare('INSERT INTO PaginationFixture VALUES (?, ?)');
for ($i = 1; $i <= 251; $i++) {
    $insert->execute([$i, 'fixture']);
}
$request = (object) ['payload' => (object) ['TABLE' => 'PaginationFixture', 'PAGE' => 3, 'LIMIT' => 500, 'CSRF' => $_SESSION['CSRF']]];
$admin->getTableData($request);
if ($admin->success !== true || $admin->pagination['totalPages'] != 3 || count($admin->data) !== 51 || $admin->pagination['limit'] !== 100) {
    throw new RuntimeException('Table limit was not clamped before pagination.');
}
$request->payload->SQL = 'SELECT * FROM PaginationFixture';
$admin->runSQLQuery($request);
if ($admin->success !== true || $admin->pagination['totalPages'] != 3 || $admin->pagination['showing'] !== '201 - 251 of 251') {
    throw new RuntimeException('SQL pagination disagrees with table pagination.');
}
$admin->db->exec('DELETE FROM PaginationFixture');
$admin->getTableData($request);
if ($admin->pagination['currentPage'] !== 1 || $admin->pagination['showing'] !== '0 - 0 of 0' || count($admin->data) !== 0) {
    throw new RuntimeException('Empty pagination is invalid.');
}
$logPath = __OHCRUD_LOG_PATH__ . 'pagination.log';
$lines = [];
for ($i = 1; $i <= 251; $i++) {
    $lines[] = json_encode(['id' => $i, 'message' => str_repeat('x', 100)]);
}
file_put_contents($logPath, implode("\n", $lines));
$request->payload->LOG = 'pagination.log';
$admin->getLogData($request);
if ($admin->pagination['totalPages'] != 3 || count($admin->data) !== 51 || $admin->data[0]->id !== 51 || $admin->data[50]->id !== 1) {
    throw new RuntimeException('Deep reverse log pagination returned the wrong records.');
}
file_put_contents($logPath, "\n" . json_encode(['id' => 252]), FILE_APPEND);
$admin->getLogData($request);
if ($admin->pagination['totalRecords'] !== 252 || count($admin->data) !== 52) {
    throw new RuntimeException('Changed log reused a stale count.');
}
$admin->clearLog($request);
$admin->getLogData($request);
if ($admin->pagination['showing'] !== '0 - 0 of 0' || count($admin->data) !== 0) {
    throw new RuntimeException('Cleared log retained records.');
}
echo "Table, SQL, empty and deep log pagination passed.\n";
