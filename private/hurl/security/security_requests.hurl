GET {{base}}/ohcrud/users/logout/
HTTP 405
[Asserts]
header "Allow" == "POST"

GET {{base}}/login/
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 400

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":[],"USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 400

POST {{base}}/ohcrud/users/login/
Origin: https://untrusted.invalid
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 403
[Asserts]
header "Access-Control-Allow-Credentials" not exists

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
[]
HTTP 400

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 200

POST {{base}}/ohcrud/users/logout/
Content-Type: application/json
{}
HTTP 400

GET {{base}}/ohcrud/themes/getThemes/
HTTP 200

POST {{base}}/ohcrud/users/logout/
Content-Type: application/json
{"CSRF":"{{csrf}}"}
HTTP 200

GET {{base}}/ohcrud/themes/getThemes/
HTTP 401
