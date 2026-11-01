GET {{base}}/login/
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 200

POST {{base}}/ohcrud/pages/save/
Content-Type: application/json
{"CSRF":"{{csrf}}","URL":"/output-test/","TITLE":"Output test","TEXT":"[[CMS/Figure/Figure|image=javascript:alert(1)|alt=Safe|caption=Visible caption]]","STATUS":1,"THEME":"../app","LAYOUT":"index"}
HTTP 400

POST {{base}}/ohcrud/pages/save/
Content-Type: application/json
{"CSRF":"{{csrf}}","URL":"/output-test/","TITLE":"Output test","TEXT":"[[CMS/Figure/Figure|image=javascript:alert(1)|alt=Safe|caption=Visible caption]]","STATUS":1}
HTTP 200

GET {{base}}/output-test/
HTTP 200
[Asserts]
body contains "Visible caption"
body not contains "javascript:alert"

POST {{base}}/ohcrud/pages/save/
Content-Type: application/json
{"CSRF":"{{csrf}}","URL":"/output-test/","TITLE":"Output test","TEXT":"Deleted content","STATUS":0}
HTTP 200

GET {{base}}/output-test/
HTTP 404

POST {{base}}/ohcrud/admin/createTableRow/
Content-Type: application/json
{"CSRF":"{{csrf}}","TABLE":"Files","NAME":"Boundary test","PATH":"/settings.php","STATUS":1}
HTTP 200
[Captures]
fileId: jsonpath "$.lastInsertId"

POST {{base}}/ohcrud/admin/deleteTableRow/
Content-Type: application/json
{"CSRF":"{{csrf}}","TABLE":"Files","KEY_COLUMN":"ID","KEY_VALUE":"{{fileId}}"}
HTTP 403

# The configuration file must still exist and the application must still work.
GET {{base}}/login/
HTTP 200
