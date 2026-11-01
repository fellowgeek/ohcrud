GET {{base}}/login/
HTTP 200
[Captures]
csrf: regex /__CSRF__ = '(.*?)'/

POST {{base}}/ohcrud/files/upload/
HTTP 401

POST {{base}}/ohcrud/users/login/
Content-Type: application/json
{"CSRF":"{{csrf}}","USERNAME":"testadmin","PASSWORD":"Test-password-42!"}
HTTP 200

POST {{base}}/ohcrud/files/upload/
[MultipartFormData]
CSRF: {{csrf}}
0: file,Mona_Lisa.jpg; image/jpeg
HTTP 200
[Asserts]
jsonpath "$.success" == true
jsonpath "$.data.TYPE" == "jpg"

# A non-image file with an allowed image extension must fail content matching.
POST {{base}}/ohcrud/files/upload/
[MultipartFormData]
CSRF: {{csrf}}
0: file,fixtures/fake.png; image/png
HTTP 415

POST {{base}}/ohcrud/files/upload/
[MultipartFormData]
CSRF: {{csrf}}
0: file,fixtures/active.svg; image/svg+xml
HTTP 415
