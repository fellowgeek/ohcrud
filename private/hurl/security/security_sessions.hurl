GET {{base}}/login/
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/
oldSession: cookie "PHPSESSID"

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 200
[Captures]
newSession: cookie "PHPSESSID"

GET {{base}}/restricted/
HTTP 200
[Asserts]
body contains "PRIVATE_MARKER"

POST {{base}}/ohcrud/users/logout/
Content-Type: application/json
{"CSRF":"{{csrf}}"}
HTTP 200

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testreader","PASSWORD":"Test-password-42!"}
HTTP 200

GET {{base}}/restricted/
HTTP 403
[Asserts]
body not contains "PRIVATE_MARKER"

GET {{base}}/ohcrud/themes/getThemes/
HTTP 403
