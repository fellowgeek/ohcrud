GET {{base}}/login/
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/

POST {{base}}/ohcrud/users/verify/
Content-Type: application/json
{"CSRF":"{{csrf}}","TOTP":"{{totp}}"}
HTTP 401

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testmfa","PASSWORD":"Test-password-42!"}
HTTP 200
[Asserts]
jsonpath "$.data.TOTPVerified" == false

GET {{base}}/ohcrud/themes/getThemes/
HTTP 401

POST {{base}}/ohcrud/users/verify/
Content-Type: application/json
{"CSRF":"{{csrf}}","TOTP":"invalid"}
HTTP 401

POST {{base}}/ohcrud/users/verify/
Content-Type: application/json
{"CSRF":"{{csrf}}","TOTP":"{{totp}}"}
HTTP 200
[Asserts]
jsonpath "$.data.loggedIn" == true

GET {{base}}/ohcrud/themes/getThemes/
HTTP 200

POST {{base}}/ohcrud/users/logout/
Content-Type: application/json
{"CSRF":"{{csrf}}"}
HTTP 200

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testmfa","PASSWORD":"Test-password-42!"}
HTTP 200

POST {{base}}/ohcrud/users/verify/
Content-Type: application/json
{"CSRF":"{{csrf}}","TOTP":"{{totp}}"}
HTTP 401
