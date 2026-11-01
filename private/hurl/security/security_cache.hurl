GET {{base}}/login/
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 200

GET {{base}}/restricted/
HTTP 200
[Asserts]
body contains "PRIVATE_MARKER"

GET {{base}}/embedded/?variant=1
HTTP 200
[Asserts]
body contains "PRIVATE_MARKER"

POST {{base}}/ohcrud/users/logout/
Content-Type: application/json
{"CSRF":"{{csrf}}"}
HTTP 200

GET {{base}}/restricted/
HTTP 403
[Asserts]
body not contains "PRIVATE_MARKER"

GET {{base}}/embedded/?variant=1
HTTP 200
[Asserts]
body not contains "PRIVATE_MARKER"

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 200

POST {{base}}/ohcrud/pages/restoreDeletePage/
Content-Type: application/json
{"CSRF":"{{csrf}}","URL":"/embedded/"}
HTTP 200
[Asserts]
jsonpath "$.success" == true

GET {{base}}/embedded/?variant=1
HTTP 404
[Asserts]
body not contains "PRIVATE_MARKER"
