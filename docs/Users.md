# Users Object

The `Users` object is the ohCRUD's way of handling users and permissions. It extends the `DB` object.

When `__OHCRUD_DEBUG_MODE__` is set to `TRUE`, if the `Users` table does not exist, ohCRUD will auto-create the table and insert a default admin user into it.

### Default Admin Credentials

*   **Username:** admin
*   **Password:** admin

## Methods

Same as `DB` object plus the following:

| Method | Description | Return Value |
| --- | --- | --- |
| `create($table, $data=array())` | Overrides the `DB::create` method to hash passwords and generate a token. | `Users` Object |
| `update($table, $data, $where, ...)` | Overrides the `DB::update` method to hash passwords if a new password is provided. | `Users` Object |
| `enableTOTP($id, $activateTOTP = true)` | Enables or re-generates TOTP for a user. | boolean |
| `enableTOKEN($id)` | Enables or re-generates an API token for a user. | boolean |
| `login($username, $password, $token = null)` | Authenticates a user with username/password or an API token. | \stdClass / boolean |
| `verify($id, $TOTP_CODE)` | Verifies a TOTP code for a user. | \stdClass / boolean |
| `generateToken($username)` | Generates a randomized API token. | string |

## Authentication Flow

### Username/Password Login
1. A login request is made with `username` and `password`.
2. The `login()` method finds the user by `USERNAME` and `STATUS`.
3. It verifies the password using `password_verify()`.
4. If successful and TOTP is disabled for the user, it creates a `User` session.
5. If TOTP is enabled, it creates a `tempUser` session and returns `TOTPVerified = false`. The frontend should then prompt for a TOTP code.

### TOTP Verification
1. After a successful password login for a TOTP-enabled user, a request is made to `verify()` with the user ID and the `TOTP_CODE`.
2. The `verify()` method validates the TOTP code against the user's secret.
3. If valid, it promotes the `tempUser` session to a full `User` session.

### API Token Login
1. A request is made with a `Token` header. The token is expected to be a concatenation of the user's email hash (SHA1) and the API token.
2. The `login()` method splits the token to get the hash and the token itself.
3. It finds the user by the `HASH` and `STATUS`.
4. It decrypts the stored token and compares it with the one provided.
5. If valid, it creates a `User` session.
