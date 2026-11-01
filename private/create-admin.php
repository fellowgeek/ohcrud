<?php
// Provision an administrator explicitly without storing or printing its password.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(); }
$GLOBALS['OHCRUD'] = true;
require __DIR__ . '/../public/vendor/autoload.php';
$username = getenv('OHCRUD_ADMIN_USERNAME');
$email = getenv('OHCRUD_ADMIN_EMAIL');
$password = getenv('OHCRUD_ADMIN_PASSWORD');
if (is_string($username) == false || preg_match('/^[a-z0-9]{3,32}$/D', $username) !== 1 ||
    filter_var($email, FILTER_VALIDATE_EMAIL) === false || is_string($password) == false || strlen($password) < 15) {
    fwrite(STDERR, "Set OHCRUD_ADMIN_USERNAME, OHCRUD_ADMIN_EMAIL and a password of at least 15 characters in OHCRUD_ADMIN_PASSWORD.\n");
    exit(1);
}
if (__OHCRUD_DEBUG_MODE__ == false && (strlen(__OHCRUD_SECRET__) < 32 || __OHCRUD_SECRET__ === 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX')) {
    fwrite(STDERR, "Configure a unique application secret first.\n");
    exit(1);
}
$users = new \ohCRUD\Users(true);
$users->create('Users', [
    'USERNAME' => $username, 'EMAIL' => $email, 'HASH' => hash('sha1', $email),
    'PASSWORD' => $password, 'NAME' => $username, 'GROUP' => 1,
    'PERMISSIONS' => 1, 'STATUS' => 1, 'TOTP' => 0,
]);
if ($users->success !== true) {
    fwrite(STDERR, "Administrator could not be created. Check private application logs.\n");
    exit(1);
}
echo "Administrator created.\n";
