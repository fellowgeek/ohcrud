# [collect SESSION & CSRF]
## Navigate to homepage and grab the PHP session cookie and CSRF token
GET https://{{host}}
[Options]
verbose: true
delay: 1s
HTTP 200
[Captures]
CSRF: regex /__CSRF__ = '(.*?)'/
PHPSESSID: cookie "PHPSESSID"
[Asserts]
body contains "ohCRUD!"
cookie "PHPSESSID" exists

## Get a 404 page
GET https://{{host}}/this-does-not-exist/
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "{{CSRF}}"
}
HTTP 404

## Get the login page
GET https://{{host}}/login/
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "{{CSRF}}"
}
HTTP 200
[Asserts]
body contains "LOGIN"

## Perform a bad login
POST https://{{host}}/ohcrud/users/login/
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "{{CSRF}}",
    "USERNAME": "bad_user",
    "PASSWORD": "bad_pasword"
}
HTTP 401
[Asserts]
jsonpath "$.success" == false
jsonpath "$.errors" exists

## Perform a bad login with bad CSRF token (debug mode should be false)
POST https://{{host}}/ohcrud/users/login/
[Options]
verbose: true
very-verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "XXXX-YYYY-ZZZZ-XXXX-YYYY-ZZZZ",
    "USERNAME": "admin",
    "PASSWORD": "admin"
}
HTTP 400
[Asserts]
jsonpath "$.success" == false
jsonpath "$.errors" exists

## Call the API to validate the user with bad ohCRUD token (should fail)
POST https://{{host}}/example/protectedEndPoint/
Token: bad_ohcrud_token_spt0q7fsb4vp9vusa67u
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "{{CSRF}}"
}
HTTP 403
[Asserts]
jsonpath "$.success" == false
jsonpath "$.errors" exists

## Call public API (should pass)
POST https://{{host}}/example/publicEndPoint/
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "{{CSRF}}"
}
HTTP 200
[Asserts]
body contains "Hello from ohCRUD!"

## Call private API (should fail)
POST https://{{host}}/ohcrud/admin/getTableList/
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "{{CSRF}}"
}
HTTP 401

## Access settings.php should get 404
GET https://{{host}}/settings.php
[Options]
verbose: true
delay: 1s
HTTP 404

## Access .git folder should get 404
GET https://{{host}}/.git/config
[Options]
verbose: true
delay: 1s
HTTP 403

## A malicious/untrusted CSRF request
POST https://{{host}}/example/whoami/
Sec-Fetch-Site: cross-site
[Options]
verbose: true
very-verbose: true
delay: 1s
HTTP 403
[Asserts]
jsonpath "$.success" == false
jsonpath "$.errors" exists
