<?php
namespace ohCRUD;

use OTPHP\TOTP;

// Prevent direct access to this class
if (isset($GLOBALS['OHCRUD']) == false) { die(); }

// Class Users - Users class for ohCRUD, this class handles creation and authentication of the ohCRUD framework users.
class Users extends \ohCRUD\DB {

    function __construct($initializeSchema = false) {
        parent::__construct();

        // Initialize schema only in debug mode or during explicit CLI provisioning.
        if (__OHCRUD_DEBUG_MODE__ == true || ($initializeSchema == true && PHP_SAPI === 'cli')) {

            // Check if the Users table exists and create it if it doesn't
            $tableExists = false;

            switch($this->config["DRIVER"]) {
                case "SQLITE":
                    $tableExists = $this->run("SELECT * FROM sqlite_master WHERE `name`='Users';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Users` (
                            `ID` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
                            `USERNAME` TEXT,
                            `EMAIL` TEXT,
                            `HASH` TEXT,
                            `PASSWORD` TEXT,
                            `NAME` TEXT,
                            `GROUP`	INTEGER,
                            `PERMISSIONS` INTEGER,
                            `TOKEN` TEXT,
                            `TOTP_SECRET` TEXT,
                            `STATUS` INTEGER,
                            `TOTP` INTEGER
                        );
                    ";
                    $this->run($sql);

                    $sql = "CREATE UNIQUE INDEX IF NOT EXISTS idx_USERNAME ON Users (`USERNAME`);";
                    $this->run($sql);

                    $sql = "CREATE UNIQUE INDEX IF NOT EXISTS idx_EMAIL ON Users (`EMAIL`);";
                    $this->run($sql);

                    $sql = "CREATE UNIQUE INDEX IF NOT EXISTS idx_HASH ON Users (`HASH`);";
                    $this->run($sql);

                    $sql = "CREATE UNIQUE INDEX IF NOT EXISTS idx_TOKEN ON Users (`TOKEN`);";
                    $this->run($sql);
                    break;

                case "MYSQL":
                    $tableExists = $this->run("SELECT * FROM information_schema.tables WHERE `table_schema`='" . $this->config["MYSQL_DB"] . "' AND `table_name`= 'Users';")->first() ?? false;
                    $sql = "CREATE TABLE IF NOT EXISTS `Users` (
                        `ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
                        `USERNAME` varchar(128) NOT NULL DEFAULT '',
                        `EMAIL` varchar(128) NOT NULL DEFAULT '',
                        `HASH` varchar(128) NOT NULL DEFAULT '',
                        `PASSWORD` varchar(256) NOT NULL DEFAULT '',
                        `NAME` varchar(64) NOT NULL DEFAULT '',
                        `GROUP` int(10) unsigned NOT NULL DEFAULT 0,
                        `PERMISSIONS` int(10) unsigned NOT NULL DEFAULT 0,
                        `TOKEN` varchar(256) NOT NULL DEFAULT '',
                        `TOTP_SECRET` varchar(256) NOT NULL DEFAULT '',
                        `STATUS` tinyint(1) NOT NULL DEFAULT 0,
                        `TOTP` int(10) unsigned NOT NULL DEFAULT 0,
                        PRIMARY KEY (`ID`),
                        UNIQUE KEY `idx_USERNAME` (`USERNAME`) USING BTREE,
                        UNIQUE KEY `idx_EMAIL` (`EMAIL`) USING BTREE,
                        UNIQUE KEY `idx_HASH` (`HASH`) USING BTREE,
                        UNIQUE KEY `idx_TOKEN` (`TOKEN`) USING BTREE,
                        KEY `idx_GROUP` (`GROUP`) USING BTREE,
                        KEY `idx_STATUS` (`STATUS`) USING BTREE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
                    ";
                    $this->run($sql);
                    break;
            }

            // Account creation is explicit; never install a publicly known password.
            if ($tableExists === false && $this->success === true) {
                $this->log('notice', 'Users schema initialized. Provision an administrator through the CLI.');
            }
        }
    }

    // Override the create function to include password hashing and token generation
    public function create($table, $data=array()) {
        // create hash from password
        if (isset($data['PASSWORD']) == true) {
            $data['PASSWORD'] = password_hash(
                $data['PASSWORD'] . __OHCRUD_SECRET__, PASSWORD_BCRYPT, [
                    'cost' => 14
                ]
            );
        }

        // Generate an API access token based on the username
        if (isset($data['USERNAME']) == true) {
            $data['TOKEN'] = $this->encryptText($this->generateToken($data['USERNAME']));
        }

        return parent::create($table, $data);
    }

    // Override the update function to include password hashing and token generation
    public function update($table, $data, $where, $bind=array()) {

        // create hash from password
        if (isset($data['PASSWORD']) == true) {
            $data['PASSWORD'] = password_hash(
                $data['PASSWORD'] . __OHCRUD_SECRET__, PASSWORD_BCRYPT, [
                    'cost' => 14
                ]
            );
        }

        return parent::update($table, $data, $where, $bind);
    }

    // Legacy callers must use the confirmed enrollment flow instead of replacing a live factor.
    public function enableTOTP($id, $activateTOTP = true) {
        return false;
    }

    // Reauthenticate the current user before changing their authenticator.
    private function reauthenticateForTOTP($password, $code) {
        $id = $_SESSION['User']->ID ?? null;
        if ($id === null || is_string($password) == false ||
            $this->allowAuthenticationAttempt('factor-password:' . $id, 5) == false) {
            return false;
        }
        $user = $this->read('Users', 'ID = :ID AND STATUS = 1', [':ID' => $id])->first();
        if ($user === false || password_verify($password . __OHCRUD_SECRET__, $user->PASSWORD) == false) {
            return false;
        }
        if ((int) $user->TOTP === $this::ACTIVE &&
            ($this->allowAuthenticationAttempt('totp:' . $id, 5) == false || $this->consumeTOTPCode($user, $code) == false)) {
            return false;
        }
        return $user;
    }

    // Keep a replacement secret pending until its owner proves possession.
    public function beginTOTP($password, $code = null) {
        $this->unsetSession('pendingTOTP');
        $user = $this->reauthenticateForTOTP($password, $code);
        if ($user === false) {
            return false;
        }
        $totp = TOTP::generate();
        $totp->setLabel($user->USERNAME);
        $totp->setIssuer(__APP__);
        $this->setSession('pendingTOTP', (object) [
            'ID' => $user->ID, 'created' => $this->authenticationTime(),
            'fingerprint' => $this->authenticationFingerprint($user),
            'secret' => $this->encryptText($totp->getSecret()),
        ]);
        return (object) ['QR_CODE' => $totp->getProvisioningUri()];
    }

    // Activate only the pending secret belonging to this authenticated session.
    public function confirmTOTP($code) {
        $pending = $_SESSION['pendingTOTP'] ?? null;
        if (isset($pending->ID, $pending->created, $pending->fingerprint, $pending->secret) == false ||
            (int) $pending->ID !== (int) ($_SESSION['User']->ID ?? 0) ||
            $this->authenticationTime() - $pending->created >= 300) {
            $this->unsetSession('pendingTOTP');
            return false;
        }
        $user = $this->read('Users', 'ID = :ID AND STATUS = 1', [':ID' => $pending->ID])->first();
        if ($user === false || hash_equals($this->authenticationFingerprint($user), $pending->fingerprint) == false ||
            $this->allowAuthenticationAttempt('enrollment:' . $pending->ID, 5) == false) {
            return false;
        }
        $replacement = clone $user;
        $replacement->TOTP_SECRET = $pending->secret;
        if ($this->consumeTOTPCode($replacement, $code) == false) {
            return false;
        }
        return $this->replaceTOTP($user, $pending->secret, $this::ACTIVE);
    }

    // Disabling a factor requires the password and the currently enrolled factor.
    public function disableTOTP($password, $code) {
        $user = $this->reauthenticateForTOTP($password, $code);
        return $user !== false ? $this->replaceTOTP($user, '', $this::INACTIVE) : false;
    }

    // Compare-and-swap prevents concurrent password or factor changes being overwritten.
    private function replaceTOTP($user, $secret, $status) {
        $statement = $this->db->prepare("UPDATE Users SET TOTP_SECRET = :secret, TOTP = :status
            WHERE ID = :id AND STATUS = 1 AND PASSWORD = :password AND COALESCE(TOTP_SECRET, '') = :oldSecret");
        $statement->execute([':secret' => $secret, ':status' => $status, ':id' => $user->ID,
            ':password' => $user->PASSWORD, ':oldSecret' => $user->TOTP_SECRET ?? '']);
        if ($statement->rowCount() !== 1) {
            return false;
        }
        $this->unsetSession('pendingTOTP');
        $user = $this->read('Users', 'ID = :ID', [':ID' => $user->ID])->first();
        $this->createUserSession($user);
        return true;
    }

    // Enable or re-generate TOKEN for a user
    public function enableTOKEN($id) {
        $user = $this->read(
            'Users',
            'ID = :ID',
            [
                ':ID' => $id
            ]
        )->first();

        if ($user != false) {
            $token = $this->encryptText($this->generateToken($user->USERNAME));

            $output = $this->Update(
                'Users',
                [
                    'TOKEN' => $token
                ],
                'ID = :ID',
                [
                    ':ID' => $id
                ]
            )->success;

            return $output;
        } else {
            return false;
        }
    }

    // Authenticate and check if the user has TOTP enabled
    public function login($username, $password, $token = null) {

        // A new password attempt supersedes any previous MFA challenge.
        $this->unsetSession('tempUser');
        if ($this->allowAuthenticationAttempt('ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 100) == false) {
            return false;
        }

        // Handle API token-based logins
        if (isset($token) == true) {

            // Validate the token and extract the token parts
            if (is_string($token) == true && preg_match('/^[a-f0-9]{80}$/D', $token) === 1) {
                $hash = substr($token, 0, 40);
                $APIToken = substr($token, 40, 40);
            } else {
                $this->log('warning', 'Invalid token length', []);
                // Delay to mitigate brute force and timing attacks
                usleep(rand(500000, 1000000));
                return false;
            }

            // Get a user based on the token and status
            $user = $this->read(
                'Users',
                'HASH = :HASH AND STATUS = :STATUS',
                [
                    ':HASH' => $hash,
                    ':STATUS' => $this::ACTIVE
                ]
            )->first();

            // Return if user was not found
            if ($user === false) {
                $this->log('warning', 'Invalid token hash', []);
                // Delay to mitigate brute force and timing attacks
                usleep(rand(500000, 1000000));
                return false;
            }

            // Decrypt and compare user token
            $storedToken = $this->decryptText($user->TOKEN);
            if (is_string($storedToken) == true && hash_equals($storedToken, $APIToken) == true) {
                $this->releaseAuthenticationAttempt('ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));
                $user = $this->createUserSession($user);
            } else {
                $this->log('warning', 'Invalid token', []);
                // Delay to mitigate brute force and timing attacks
                usleep(rand(500000, 1000000));
                return false;
            }

            return $user;
        }

        // Throttle by account as well as source; changing sessions does not reset limits.
        if (is_string($username) == false || is_string($password) == false ||
            strlen($username) > 128 || strlen($password) > 4096 ||
            $this->allowAuthenticationAttempt('password:' . strtolower($username), 10) == false) {
            return false;
        }

        // Get a user based on username and status
        $user = $this->read(
            'Users',
            'USERNAME = :USERNAME AND STATUS = :STATUS',
            [
                ':USERNAME' => $username,
                ':STATUS' => $this::ACTIVE
            ]
        )->first();

        if ($user !== false) {
            // Verify the user's password against the stored hash
            $user->loggedIn = password_verify($password . __OHCRUD_SECRET__, $user->PASSWORD);
            if ($user->loggedIn == false) {
                $this->log('warning', 'Login attempt was not successful', [$username]);
                return false;
            }

            // A verified password does not consume the source-IP failure allowance.
            $this->releaseAuthenticationAttempt('ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'));

            // Check if the user has TOTP enabled
            if ((int) $user->TOTP == $this::ACTIVE) {
                $user->TOTPVerified = false;
                $user->challengeCreated = $this->authenticationTime();
                $user->challengeFingerprint = $this->authenticationFingerprint($user);
                $this->sanitizeUser($user);
                $this->unsetSession('User');
                $this->unsetSession('authentication');
                $this->regenerateSession();
                $this->setSession('tempUser', $user);
            }

            // Create the user session and log in the user if TOTP is not enabled for this user
            if ((int) $user->TOTP == $this::INACTIVE) {
                $user = $this->createUserSession($user);
            }
        }

        return $user;
    }

    // Handle TOTP authentication for a given user ID
    public function verify($id, $TOTP_CODE) {

        // Bind verification to a recent password challenge for this exact account.
        $challenge = $_SESSION['tempUser'] ?? null;
        $now = $this->authenticationTime();
        if (isset($challenge->ID, $challenge->challengeCreated, $challenge->challengeFingerprint) == false ||
            (int) $challenge->ID !== (int) $id || $now - $challenge->challengeCreated >= 300 ||
            $now < $challenge->challengeCreated) {
            $this->unsetSession('tempUser');
            return false;
        }
        if ($this->allowAuthenticationAttempt('totp:' . $id, 5) == false ||
            is_string($TOTP_CODE) == false || preg_match('/^[0-9]{6}$/D', $TOTP_CODE) !== 1) {
            return false;
        }

        // Get the user
        $user = $this->read(
            'Users',
            'ID = :ID AND STATUS = :STATUS',
            [
                ':ID' => $id,
                ':STATUS' => $this::ACTIVE
            ]
        )->first();

        if ($user === false || (int) $user->TOTP !== $this::ACTIVE ||
            hash_equals($this->authenticationFingerprint($user), $challenge->challengeFingerprint) == false) {
            $this->unsetSession('tempUser');
            $this->log('warning', 'TOTP challenge is no longer valid.', [$id]);
            return false;
        }

        if ($this->consumeTOTPCode($user, $TOTP_CODE) == false) {
            return false;
        }

        // Rotate the session only after both factors have been verified.
        return $this->createUserSession($user);
    }

    // Verify and consume a code once, including during enrollment or factor changes.
    private function consumeTOTPCode($user, $TOTP_CODE) {
        if (is_string($TOTP_CODE) == false || preg_match('/^[0-9]{6}$/D', $TOTP_CODE) !== 1) {
            return false;
        }
        $now = $this->authenticationTime();
        // Create a TOTP object from the secret and verify the TOTP code
        $secret = $this->decryptText($user->TOTP_SECRET);
        if (is_string($secret) == false || preg_match('/^[A-Z2-7]+$/D', $secret) !== 1) {
            return false;
        }
        $totp = TOTP::createFromSecret($secret);

        if ($totp->verify($TOTP_CODE, $now) == false) {
            $this->log('warning', 'TOTP verification failed.', [$user->USERNAME]);
            return false;
        }

        // Atomically consume the time step across sessions and concurrent requests.
        $key = 'replay:' . $user->ID . ':' . hash('sha256', $secret);
        if ($this->initializeAuthenticationState($key) == false) {
            return false;
        }
        $statement = $this->db->prepare('UPDATE AuthState SET LAST_STEP = :step WHERE ID = :id AND LAST_STEP < :previous');
        $step = intdiv($now, $totp->getPeriod());
        $statement->execute([':step' => $step, ':id' => hash('sha256', $key), ':previous' => $step]);
        return $statement->rowCount() === 1;

    }

    // Keep time-dependent authentication checks testable without waiting or sleeping.
    protected function authenticationTime() {
        return time();
    }

    // Create durable counters using an atomic insert on either supported database.
    private function initializeAuthenticationState($key) {
        try {
            $sql = $this->config['DRIVER'] === 'SQLITE'
                ? 'INSERT OR IGNORE INTO AuthState (ID) VALUES (:id)'
                : 'INSERT IGNORE INTO AuthState (ID) VALUES (:id)';
            return $this->db->prepare($sql)->execute([':id' => hash('sha256', $key)]);
        } catch (\PDOException $e) {
            $this->log('error', 'Authentication state unavailable; apply the security migration.');
            return false;
        }
    }

    // Successful credentials release only their own reservation, not other failures.
    private function releaseAuthenticationAttempt($key) {
        $statement = $this->db->prepare('UPDATE AuthState SET ATTEMPTS = CASE WHEN ATTEMPTS > 0 THEN ATTEMPTS - 1 ELSE 0 END WHERE ID = :id');
        $statement->execute([':id' => hash('sha256', $key)]);
    }

    // Consume an attempt atomically; cookies and parallel workers share these limits.
    private function allowAuthenticationAttempt($key, $limit) {
        if ($this->initializeAuthenticationState($key) == false) {
            return false;
        }
        $now = $this->authenticationTime();
        $statement = $this->db->prepare('UPDATE AuthState SET
            ATTEMPTS = CASE WHEN WINDOW_START <= :expired THEN 1 ELSE ATTEMPTS + 1 END,
            WINDOW_START = CASE WHEN WINDOW_START <= :expiredAgain THEN :now ELSE WINDOW_START END
            WHERE ID = :id AND (WINDOW_START <= :expiredWhere OR ATTEMPTS < :limit)');
        $statement->execute([
            ':expired' => $now - 900, ':expiredAgain' => $now - 900,
            ':now' => $now, ':id' => hash('sha256', $key),
            ':expiredWhere' => $now - 900, ':limit' => $limit,
        ]);
        return $statement->rowCount() === 1;
    }

    // Bind sessions and pending challenges to the current account security state.
    private function authenticationFingerprint($user) {
        return hash_hmac('sha256', json_encode([
            $user->ID, $user->PASSWORD, $user->TOKEN, $user->TOTP_SECRET,
            $user->STATUS, $user->PERMISSIONS, $user->TOTP, $user->USERNAME,
        ]), __OHCRUD_SECRET__);
    }

    // Complete authentication consistently for passwords, TOTP and API tokens.
    private function createUserSession($user) {
        $statement = $this->db->prepare('UPDATE AuthState SET ATTEMPTS = 0 WHERE ID = :password OR ID = :totp');
        $statement->execute([
            ':password' => hash('sha256', 'password:' . strtolower($user->USERNAME)),
            ':totp' => hash('sha256', 'totp:' . $user->ID),
        ]);
        $authentication = (object) [
            'fingerprint' => $this->authenticationFingerprint($user),
            'created' => time(),
            'lastUsed' => time(),
        ];
        $user->loggedIn = true;
        $this->sanitizeUser($user);
        $this->regenerateSession();
        $this->unsetSession('tempUser');
        $this->unsetSession('pendingTOTP');
        $this->setSession('authentication', $authentication);
        $this->setSession('User', $user);
        return $user;
    }

    // Revalidate authorization and credentials before API or CMS session use.
    public function validateSession() {
        if (isset($_SESSION['User']->ID) == false) {
            return false;
        }
        $user = $this->read('Users', 'ID = :ID', [':ID' => $_SESSION['User']->ID])->first();
        $authentication = $_SESSION['authentication'] ?? null;
        if ($user === false || (int) $user->STATUS !== $this::ACTIVE ||
            isset($authentication->fingerprint, $authentication->created, $authentication->lastUsed) == false ||
            time() - $authentication->created >= __OHCRUD_SESSION_LIFETIME__ ||
            time() - $authentication->lastUsed >= __OHCRUD_SESSION_LIFETIME__ ||
            hash_equals($this->authenticationFingerprint($user), $authentication->fingerprint) == false) {
            $this->unsetSession('User');
            $this->unsetSession('authentication');
            $this->unsetSession('tempUser');
            $this->unsetSession('pendingTOTP');
            $this->regenerateSession();
            return false;
        }
        $authentication->lastUsed = time();
        $this->setSession('authentication', $authentication);
        return true;
    }

    // Helper to clean user object
    private function sanitizeUser(&$user) {
        unset($user->PASSWORD);
        unset($user->HASH);
        unset($user->TOKEN);
        unset($user->TOTP_SECRET);
    }

    // Generate a randomized API token based on the username
    public function generateToken($username) {
        $randomString = bin2hex(random_bytes(32));
        return hash('sha1', __OHCRUD_SECRET__ . $username . $randomString . time());
    }

}
