<?php
// Use the real OTP library and database, with a fixed clock and isolated sessions.
$GLOBALS['OHCRUD'] = true;
require __DIR__ . '/autoload.php';
class TestUsers extends \ohCRUD\Users {
    public $clock = 1800000000;
    protected function authenticationTime() { return $this->clock; }
    public function setSession($key, $value) { $_SESSION[$key] = $value; return $this; }
    public function unsetSession($key) { unset($_SESSION[$key]); return $this; }
    public function regenerateSession() { return $this; }
}
function check($condition, $message) {
    if ($condition !== true) { throw new RuntimeException($message); }
}
$users = new TestUsers;
$totp = \OTPHP\TOTP::createFromSecret('JBSWY3DPEHPK3PXP');
$reset = function () use ($users) {
    $_SESSION = [];
    $users->db->exec('DELETE FROM AuthState');
};
$login = function () use ($users) {
    return $users->login('testmfa', 'Test-password-42!');
};
$reset();
check($users->verify(2, $totp->at($users->clock)) === false, 'Verification without password challenge succeeded.');
check($login() !== false && isset($_SESSION['User']) === false, 'Password bypassed MFA.');
check($users->verify(2, '123') === false, 'Malformed code accepted.');
check($users->verify(2, $totp->at($users->clock)) !== false, 'Valid code rejected.');
check(isset($_SESSION['User']) === true && isset($_SESSION['tempUser']) === false, 'Challenge not consumed.');
$login();
check($users->verify(2, $totp->at($users->clock)) === false, 'TOTP replay accepted.');
$users->clock += 30;
check($users->verify(2, $totp->at($users->clock)) !== false, 'Next time step rejected.');
$reset();
$login();
$users->clock += 300;
check($users->verify(2, $totp->at($users->clock)) === false, 'Expired challenge accepted.');
$reset();
$login();
$users->db->exec('UPDATE Users SET TOTP = 0 WHERE ID = 2');
check($users->verify(2, $totp->at($users->clock)) === false, 'Disabled TOTP challenge accepted.');
$users->db->exec('UPDATE Users SET TOTP = 1 WHERE ID = 2');
$reset();
$login();
for ($attempt = 0; $attempt < 5; $attempt++) {
    check($users->verify(2, 'bad') === false, 'Bad code accepted.');
}
// Starting a new session/challenge cannot reset the account's attempt counter.
$_SESSION = [];
$login();
check($users->verify(2, $totp->at($users->clock)) === false, 'MFA rate limit reset by new session.');
$users->clock += 900;
$login();
check($users->verify(2, $totp->at($users->clock)) !== false, 'Rate limit did not expire.');
$reset();
for ($attempt = 0; $attempt < 10; $attempt++) { $users->login('testmfa', 'wrong'); }
check($login() === false, 'Password attempts not limited.');
$reset();
check($users->login(null, null, str_repeat('a', 40) . str_repeat('b', 40)) !== false, 'API token unexpectedly requires TOTP.');
// Stateless API clients may authenticate frequently without exhausting failure limits.
for ($attempt = 0; $attempt < 110; $attempt++) {
    $_SESSION = [];
    check($users->login(null, null, str_repeat('a', 40) . str_repeat('b', 40)) !== false, 'Valid token traffic exhausted the IP failure allowance.');
}
check($users->validateSession() === true, 'Fresh token session invalid.');
$users->db->exec('UPDATE Users SET PERMISSIONS = 2 WHERE ID = 2');
check($users->validateSession() === false, 'Demoted account retained its session.');
$users->db->exec('UPDATE Users SET PERMISSIONS = 1 WHERE ID = 2');
$reset();
$users->login('testadmin', 'Test-password-42!');
check($users->beginTOTP('wrong') === false, 'Enrollment accepted a wrong password.');
$setup = $users->beginTOTP('Test-password-42!');
check($setup !== false, 'Enrollment did not start.');
parse_str(parse_url($setup->QR_CODE, PHP_URL_QUERY), $parameters);
$newFactor = \OTPHP\TOTP::createFromSecret($parameters['secret']);
check((int) $users->read('Users', 'ID = 1')->first()->TOTP === 0, 'Unconfirmed factor was enabled.');
check($users->confirmTOTP('bad') === false, 'Invalid enrollment code accepted.');
check($users->confirmTOTP($newFactor->at($users->clock)) === true, 'Enrollment confirmation failed.');
check($users->disableTOTP('Test-password-42!', $newFactor->at($users->clock)) === false, 'Enrollment code replayed to disable MFA.');
$users->clock += 30;
check($users->disableTOTP('Test-password-42!', $newFactor->at($users->clock)) === true, 'Factor removal with valid credentials failed.');
$setup = $users->beginTOTP('Test-password-42!');
$users->clock += 300;
check($users->confirmTOTP('123456') === false, 'Expired enrollment accepted.');
$reset();
echo "TOTP challenges, replay, throttling, enrollment and token compatibility passed.\n";
