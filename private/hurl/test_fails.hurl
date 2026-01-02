# [collect SESSION & CSRF]
## 0. navigate to homepage and grab the PHP session cookie and CSRF token
GET https://{{host}}
[Options]
verbose: true
delay: 1s
HTTP 200
[Captures]
CSRF: regex /__CSRF__ = '(.*?)'/
PHPSESSID: cookie "PHPSESSID"
[Asserts]
xpath "string(/html/body/footer/p)" contains "CMS powered by ohCRUD!"
cookie "PHPSESSID" exists

## 1. call the API to validate the user with bad ohCRUD token (should fail)
POST https://{{host}}/api/check/checkUser/
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

## 2. call the API to validate the user with bad CSRF token (should fail)
POST https://{{host}}/api/check/checkUser/
Token: {{ohcrud_token}}
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "bad_csrf_spt0q7fsb4vp9vusa67u"
}
HTTP 500
[Asserts]
jsonpath "$.success" == false
jsonpath "$.errors" exists

## 3. get a HTML page that does not exists (should 404)
GET https://{{host}}/page/does_not_exist/{{newUuid}}
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
HTTP 404
[Asserts]
body contains "You just got 404'd."

## 4. call the API that does not exists in HTTP mode (should fail)
POST https://{{host}}/api/check/onlyCLI/
Token: {{ohcrud_token}}
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

## 5. try to access .git/config file (should fail)
GET https://{{host}}/.git/config
[Options]
verbose: true
delay: 1s
HTTP 403
