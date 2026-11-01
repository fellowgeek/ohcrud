# Run with python3 private/tests/http.py; never uses the developer database.
GET {{base}}/login/?redirect=/%22%3E%3Cscript%3Ewindow.audit=1%3C/script%3E
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/
[Asserts]
body not contains "<script>window.audit=1</script>"
body contains "&quot;&gt;&lt;script&gt;"

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!","REDIRECT":"https://example.invalid/"}
HTTP 200
[Asserts]
jsonpath "$.success" == true
jsonpath "$.data.REDIRECT" == "/"

GET {{base}}/login/?redirect=//example.invalid/
HTTP 200
[Asserts]
body contains "name=\"REDIRECT_FULL\" value=\"/\""
