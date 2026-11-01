<?php
// Seed only the disposable SQLite database supplied by the test runner.
$db = new PDO('sqlite:' . getenv('OHCRUD_TEST_ROOT') . '/test.sqlite');
$db->exec('CREATE TABLE Users (ID INTEGER PRIMARY KEY, USERNAME TEXT UNIQUE, EMAIL TEXT, HASH TEXT, PASSWORD TEXT, NAME TEXT, "GROUP" INTEGER, PERMISSIONS INTEGER, TOKEN TEXT, TOTP_SECRET TEXT, STATUS INTEGER, TOTP INTEGER)');
$db->exec('CREATE TABLE Pages (ID INTEGER PRIMARY KEY, URL TEXT UNIQUE, TITLE TEXT, TEXT TEXT, "GROUP" INTEGER, PERMISSIONS INTEGER DEFAULT -1, THEME TEXT, LAYOUT TEXT, STATUS INTEGER)');
$db->exec('CREATE TABLE Files (ID INTEGER PRIMARY KEY, NAME TEXT, PATH TEXT, SIZE INTEGER, TYPE TEXT, W INTEGER, H INTEGER, IP TEXT, STATUS INTEGER)');
$stmt = $db->prepare('INSERT INTO Users (ID, USERNAME, PASSWORD, PERMISSIONS, STATUS, TOTP) VALUES (1, ?, ?, 1, 1, 0)');
$stmt->execute(['testadmin', password_hash('Test-password-42!' . 'isolated-test-secret', PASSWORD_BCRYPT, ['cost' => 4])]);
$db->exec("INSERT INTO Pages (URL, TITLE, TEXT, THEME, LAYOUT, STATUS) VALUES ('/', 'Test home', 'Disposable security test site.', 'focus', 'index', 1)");

// Include restricted content to exercise both direct and embedded authorization.
$db->exec("INSERT INTO Pages (URL, TITLE, TEXT, PERMISSIONS, THEME, LAYOUT, STATUS) VALUES ('/restricted/', 'Restricted', 'PRIVATE_MARKER', 1, 'focus', 'index', 1)");
$db->exec("INSERT INTO Pages (URL, TITLE, TEXT, PERMISSIONS, THEME, LAYOUT, STATUS) VALUES ('/embedded/', 'Embedded', '{{restricted}}', -1, 'focus', 'index', 1)");
