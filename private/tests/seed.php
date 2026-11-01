<?php
// Seed only the disposable SQLite database supplied by the test runner.
$db = new PDO('sqlite:' . getenv('OHCRUD_TEST_ROOT') . '/test.sqlite');
$db->exec(file_get_contents(__DIR__ . '/../migrations/001-auth-state.sql'));
$db->exec('CREATE TABLE Users (ID INTEGER PRIMARY KEY, USERNAME TEXT UNIQUE, EMAIL TEXT, HASH TEXT, PASSWORD TEXT, NAME TEXT, "GROUP" INTEGER, PERMISSIONS INTEGER, TOKEN TEXT, TOTP_SECRET TEXT, STATUS INTEGER, TOTP INTEGER)');
$db->exec('CREATE TABLE Pages (ID INTEGER PRIMARY KEY, URL TEXT UNIQUE, TITLE TEXT, TEXT TEXT, "GROUP" INTEGER, PERMISSIONS INTEGER DEFAULT -1, THEME TEXT, LAYOUT TEXT, STATUS INTEGER)');
$db->exec('CREATE TABLE Files (ID INTEGER PRIMARY KEY, NAME TEXT, PATH TEXT, SIZE INTEGER, TYPE TEXT, W INTEGER, H INTEGER, IP TEXT, STATUS INTEGER)');
$stmt = $db->prepare('INSERT INTO Users (ID, USERNAME, PASSWORD, PERMISSIONS, STATUS, TOTP) VALUES (1, ?, ?, 1, 1, 0)');
$stmt->execute(['testadmin', password_hash('Test-password-42!' . 'isolated-test-secret', PASSWORD_BCRYPT, ['cost' => 4])]);
$db->exec("INSERT INTO Pages (URL, TITLE, TEXT, THEME, LAYOUT, STATUS) VALUES ('/', 'Test home', 'Disposable security test site.', 'focus', 'index', 1)");

// Include restricted content to exercise both direct and embedded authorization.
$db->exec("INSERT INTO Pages (URL, TITLE, TEXT, PERMISSIONS, THEME, LAYOUT, STATUS) VALUES ('/restricted/', 'Restricted', 'PRIVATE_MARKER', 1, 'focus', 'index', 1)");
$db->exec("INSERT INTO Pages (URL, TITLE, TEXT, PERMISSIONS, THEME, LAYOUT, STATUS) VALUES ('/embedded/', 'Embedded', '{{restricted}}', -1, 'focus', 'index', 1)");

// Seed a second account for MFA routing and token compatibility checks.
$stmt = $db->prepare('INSERT INTO Users (ID, USERNAME, PASSWORD, PERMISSIONS, STATUS, TOTP, TOTP_SECRET, HASH, TOKEN) VALUES (2, ?, ?, 1, 1, 1, ?, ?, ?)');
$encrypt = function ($text) {
    $iv = random_bytes(16);
    return base64_encode($iv . openssl_encrypt($text, 'aes-256-cbc', hash('sha256', 'isolated-test-secret', true), 0, $iv));
};
$stmt->execute(['testmfa', password_hash('Test-password-42!' . 'isolated-test-secret', PASSWORD_BCRYPT, ['cost' => 4]), $encrypt('JBSWY3DPEHPK3PXP'), str_repeat('a', 40), $encrypt(str_repeat('b', 40))]);

// Small fixtures exercise image limits without allocating large images.
$image = imagecreatetruecolor(2, 10);
imagepng($image, getenv('OHCRUD_TEST_ROOT') . '/global/files/test.png');
file_put_contents(getenv('OHCRUD_TEST_ROOT') . '/global/files/bad.png', 'not an image');
