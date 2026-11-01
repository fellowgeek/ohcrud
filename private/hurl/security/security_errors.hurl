GET {{base}}/login/
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 200

POST {{base}}/ohcrud/admin/readTableRow/
Content-Type: application/json
{"CSRF":"{{csrf}}","TABLE":"MissingAuditTable","KEY_COLUMN":"ID","KEY_VALUE":1}
HTTP 500
[Asserts]
body not contains "SQLSTATE"
body not contains "no such table"
body not contains "test.sqlite"

POST {{base}}/ohcrud/admin/runSQLQuery/
Content-Type: application/json
{"CSRF":"{{csrf}}","SQL":"SELECT 1"}
HTTP 403
