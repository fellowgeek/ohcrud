GET {{base}}/ohcrud/files/image/
HTTP 400
[Asserts]
jsonpath "$.success" == false

GET {{base}}/ohcrud/files/image/?filename=missing.png
HTTP 404

GET {{base}}/ohcrud/files/image/?filename=test.png&w=4000
HTTP 400

GET {{base}}/ohcrud/files/image/?filename=test.png&h=-1
HTTP 400

GET {{base}}/ohcrud/files/image/?filename=test.png&w=1.5
HTTP 400

GET {{base}}/ohcrud/files/image/?filename=test.png&q=101
HTTP 400

# Input width is legal but its proportional height would exceed the limit.
GET {{base}}/ohcrud/files/image/?filename=test.png&w=1000
HTTP 400

GET {{base}}/ohcrud/files/image/?filename=bad.png
HTTP 415

GET {{base}}/ohcrud/files/image/?filename=test.png&w=20&h=20
HTTP 200
[Asserts]
header "Content-Type" == "image/png"
header "X-Content-Type-Options" == "nosniff"

GET {{base}}/ohcrud/files/image/?filename=test.png
HTTP 200
[Asserts]
header "Content-Type" == "image/png"
