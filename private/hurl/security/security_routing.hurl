# Methods omitted on the attribute allow every HTTP verb.
PATCH {{base}}/example/whoami/
HTTP 200
[Asserts]
jsonpath "$.data" == "unknown"

GET {{base}}/example/publicEndPoint/
HTTP 200

POST {{base}}/example/publicEndPoint/
HTTP 405
[Asserts]
header "Allow" == "GET"

HEAD {{base}}/example/publicEndPoint/
HTTP 405

# Inherited helpers, missing handlers and bare API prefixes never fall through to CMS.
GET {{base}}/example/read/
HTTP 404

GET {{base}}/example/__construct/
HTTP 404

GET {{base}}/example/missing/
HTTP 404

GET {{base}}/example/
HTTP 404

OPTIONS {{base}}/example/read/
Origin: http://localhost
Access-Control-Request-Method: GET
HTTP 404

OPTIONS {{base}}/example/publicEndPoint/
Origin: http://localhost
Access-Control-Request-Method: GET
HTTP 200
[Asserts]
header "Access-Control-Allow-Methods" == "GET"

OPTIONS {{base}}/example/publicEndPoint/
Origin: http://localhost
Access-Control-Request-Method: POST
HTTP 405
[Asserts]
header "Allow" == "GET"

GET {{base}}/example/protectedEndPoint/
HTTP 401

GET {{base}}/login/
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testreader","PASSWORD":"Test-password-42!"}
HTTP 200

GET {{base}}/example/protectedEndPoint/
HTTP 403

POST {{base}}/ohcrud/admin/getTableList/
Content-Type: application/json
{"CSRF":"{{csrf}}"}
HTTP 403

POST {{base}}/ohcrud/users/logout/
Content-Type: application/json
{"CSRF":"{{csrf}}"}
HTTP 200

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 200

GET {{base}}/example/protectedEndPoint/
HTTP 200
