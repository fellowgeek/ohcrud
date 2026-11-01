#!/usr/bin/env python3
"""Check old and new session IDs using exactly one Cookie header per request."""
import http.client
from http.cookies import SimpleCookie
import json
import re
import sys

port = int(sys.argv[1])
def request(path, cookie=None, payload=None):
    connection = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
    headers = {'Content-Type': 'application/json'} if payload is not None else {}
    if cookie:
        headers['Cookie'] = 'PHPSESSID=' + cookie
    connection.request('POST' if payload is not None else 'GET', path,
                       json.dumps(payload) if payload is not None else None, headers)
    response = connection.getresponse()
    body = response.read().decode()
    cookies = SimpleCookie()
    for key, value in response.getheaders():
        if key.lower() == 'set-cookie':
            cookies.load(value)
    session = cookies['PHPSESSID'].value if 'PHPSESSID' in cookies else cookie
    status = response.status
    connection.close()
    return status, body, session

status, body, old = request('/login/')
assert status == 200
csrf = re.search(r"__CSRF__ = '(.*?)'", body).group(1)
status, body, new = request('/ohcrud/users/login/', old, {
    'CSRF': csrf, 'USERNAME': 'testadmin', 'PASSWORD': 'Test-password-42!'
})
assert status == 200 and old != new
assert request('/ohcrud/themes/getThemes/', old)[0] == 401
assert request('/ohcrud/themes/getThemes/', new)[0] == 200
print('Old session rejected; rotated session remains authenticated.')
