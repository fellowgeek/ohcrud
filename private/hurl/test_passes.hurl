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
body contains "ohCRUD!"
cookie "PHPSESSID" exists

## ?. perform a good login (defualt inital setup admin user)
POST https://{{host}}/ohcrud/users/login/
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "{{CSRF}}",
    "USERNAME": "admin",
    "PASSWORD": "admin"
}
HTTP 200
[Captures]
PHPSESSID: cookie "PHPSESSID"
[Asserts]
jsonpath "$.success" == true

## ?. call the API to validate the user
POST https://{{host}}/example/whoami/
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
jsonpath "$.success" == true
jsonpath "$.data" == "admin"

## ?. call the API getTableList and validate base tables
POST https://{{host}}/ohcrud/admin/getTableList
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
jsonpath "$.success" == true
jsonpath "$.data.Pages" exists
jsonpath "$.data.Users" exists

## ?. call the API to create a test page
POST https://{{host}}/ohcrud/pages/save/
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "{{CSRF}}",
    "URL": "/system/test/",
    "TITLE": "System Test",
    "TEXT": "This is a test page.",
    "STATUS": 1
}
HTTP 200
[Asserts]
jsonpath "$.success" == true

## ?. get the created test page
GET https://{{host}}/system/test/
[Options]
verbose: true
delay: 1s
HTTP 200
[Asserts]
body contains "This is a test page."

## ?. edit the test page
POST https://{{host}}/ohcrud/pages/save/
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "{{CSRF}}",
    "URL": "/system/test/",
    "TITLE": "System Test 2",
    "TEXT": "This is a test page 2.",
    "STATUS": 1
}
HTTP 200
[Asserts]
jsonpath "$.success" == true

## ?. get the created test page and verify edit was successful
GET https://{{host}}/system/test/
[Options]
verbose: true
delay: 1s
HTTP 200
[Asserts]
body contains "This is a test page 2."

## ?. delete the test page
POST https://{{host}}/ohcrud/pages/restoreDeletePage/
[Options]
verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "{{CSRF}}",
    "URL": "/system/test/"
}
HTTP 200
[Asserts]
jsonpath "$.success" == true

## ?. get the created test page verify it was deleted
GET https://{{host}}/system/test/
[Options]
verbose: true
delay: 1s
HTTP 404

## ?. upload an image to the CMS end point
POST https://{{host}}/ohcrud/files/upload/?CSRF={{CSRF}}
[Options]
verbose: true
very-verbose: true
[Multipart]
0: file,Mona_Lisa.jpg;
HTTP 200
[Captures]
ID: jsonpath "$.data.ID"
IMAGE: jsonpath "$.data.PATH"
[Asserts]
jsonpath "$.success" == true

## ?. upload a forbidden file to the CMS end point
POST https://{{host}}/ohcrud/files/upload/?CSRF={{CSRF}}
[Options]
verbose: true
very-verbose: true
[Multipart]
0: file,test_passes.hurl;
HTTP 415
[Asserts]
jsonpath "$.success" == false

## ?. delete the file from the CMS
POST https://{{host}}/ohcrud/admin/deleteTableRow/
[Options]
verbose: true
very-verbose: true
delay: 1s
[Cookies]
PHPSESSID: {{PHPSESSID}}
{
    "CSRF": "{{CSRF}}",
    "TABLE": "Files",
    "KEY_COLUMN": "ID",
    "KEY_VALUE": "{{ID}}"
}
HTTP 200
[Asserts]
jsonpath "$.success" == true
