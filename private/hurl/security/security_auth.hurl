# Basic authentication for an MFA account must terminate without authorizing it.
GET {{base}}/ohcrud/themes/getThemes/
[Options]
user: testmfa:Test-password-42!
max-time: 5s
HTTP 401

# API tokens remain valid without TOTP, including case-insensitive header names.
GET {{base}}/ohcrud/themes/getThemes/
token: aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaabbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
HTTP 200
[Asserts]
jsonpath "$.success" == true
cookie "PHPSESSID[HttpOnly]" exists
cookie "PHPSESSID[SameSite]" == "Lax"

GET {{base}}/login/
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/

# Changing an account's status invalidates the existing token-created session.
POST {{base}}/ohcrud/admin/updateUserRow/
Content-Type: application/json
{"CSRF":"{{csrf}}","ID":2,"STATUS":0}
HTTP 200
[Asserts]
jsonpath "$.success" == true

GET {{base}}/ohcrud/themes/getThemes/
HTTP 401

GET {{base}}/ohcrud/themes/getThemes/
Token: aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaabbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb
HTTP 403
