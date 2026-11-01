GET {{base}}/login/
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 200

GET {{base}}/?action=server
HTTP 200
[Asserts]
body contains "ohCRUD Admin - Server"
body contains "data-name=\"server\""
body contains "Server Details"
body not contains "data-name=\"tables\""

GET {{base}}/?action=users&table=Pages
HTTP 200
[Asserts]
body contains "name=\"TABLE\" value=\"Users\""
body not contains "Admin view not found."

POST {{base}}/ohcrud/users/logout/
Content-Type: application/json
{"CSRF":"{{csrf}}"}
HTTP 200

GET {{base}}/?action=server
HTTP 200
[Asserts]
body not contains "Server Details"
body not contains "data-name=\"server\""
