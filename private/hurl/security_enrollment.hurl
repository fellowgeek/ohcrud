GET {{base}}/security/
HTTP 302
[Asserts]
header "Location" == "/login/?redirect=/security/"

GET {{base}}/login/
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 200

GET {{base}}/security/
HTTP 200
[Asserts]
body contains "Account security"
body contains "/global/js/security.js"

POST {{base}}/ohcrud/admin/getUserSecrets/
Content-Type: application/json
{"CSRF":"{{csrf}}","ID":2}
HTTP 200
[Asserts]
jsonpath "$.data.TOTP_SECRET" == false
jsonpath "$.data.QR_CODE" == false

POST {{base}}/ohcrud/admin/refreshUserSecrets/
Content-Type: application/json
{"CSRF":"{{csrf}}","ID":2,"SECRET_TYPE":"TOTP_SECRET"}
HTTP 403

POST {{base}}/ohcrud/admin/updateTableRow/
Content-Type: application/json
{"CSRF":"{{csrf}}","TABLE":"users","KEY_COLUMN":"ID","KEY_VALUE":2,"TOTP":0}
HTTP 403

POST {{base}}/ohcrud/admin/updateUserRow/
Content-Type: application/json
{"CSRF":"{{csrf}}","ID":2,"TOTP":0}
HTTP 403

POST {{base}}/ohcrud/users/beginTOTP/
Content-Type: application/json
{"CSRF":"{{csrf}}","PASSWORD":"wrong"}
HTTP 403

POST {{base}}/ohcrud/users/beginTOTP/
Content-Type: application/json
{"CSRF":"{{csrf}}","PASSWORD":"Test-password-42!"}
HTTP 200
[Asserts]
jsonpath "$.data.QR_CODE" startsWith "otpauth://totp/"

POST {{base}}/ohcrud/users/confirmTOTP/
Content-Type: application/json
{"CSRF":"{{csrf}}","TOTP":"invalid"}
HTTP 403
