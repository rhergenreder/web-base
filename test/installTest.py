import unittest
import requests
import json
import re
import string
import random

class InstallTestCase(unittest.TestCase):

    def __init__(self, args):
        super().__init__("test_install")
        self.args = args
        self.session = requests.Session()
        self.url = "http://localhost/"

        keywords = ["Fatal error", "Warning", "Notice", "Parse error", "Deprecated"]
        self.phpPattern = re.compile("<b>(%s)</b>:" % ("|".join(keywords)))

    def randomString(self, length):
       letters = string.ascii_lowercase + string.ascii_uppercase + string.digits
       return ''.join(random.choice(letters) for i in range(length))

    def httpError(self, res):
        return "Server returned: %d %s" % (res.status_code, res.reason)

    def getPhpErrors(self, res):
        return [line for line in res.text.split("\n") if self.phpPattern.search(line)]

    def test_install(self):

        # Test Connection
        res = self.session.get(self.url)
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))

        # Database Setup
        res = self.session.post(self.url, data=vars(self.args))
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))

        # Create User
        valid_username = self.randomString(16)
        valid_password = self.randomString(16)

        # 1. Invalid username
        for username in ["a", "a"*33]:
            res = self.session.post(self.url, data={ "username": username, "password": "123456", "confirmPassword": "123456" })
            self.assertEquals(200, res.status_code, self.httpError(res))
            self.assertEquals([], self.getPhpErrors(res))
            obj = json.loads(res.text)
            self.assertEquals(False, obj["success"])
            self.assertEquals("The username should be between 5 and 32 characters long", obj["msg"])

        # 2. Invalid password
        res = self.session.post(self.url, data={ "username": valid_username, "password": "1", "confirmPassword": "1" })
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
        obj = json.loads(res.text)
        self.assertEquals(False, obj["success"])
        self.assertEquals("The password should be at least 6 characters long", obj["msg"])

        # 3. Passwords do not match
        res = self.session.post(self.url, data={ "username": valid_username, "password": "1", "confirmPassword": "2" })
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
        obj = json.loads(res.text)
        self.assertEquals(False, obj["success"])
        self.assertEquals("The given passwords do not match", obj["msg"])

        # 4. User creation OK
        res = self.session.post(self.url, data={ "username": valid_username, "password": valid_password, "confirmPassword": valid_password })
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
        obj = json.loads(res.text)
        self.assertEquals(True, obj["success"])

        # Mail: SKIP
        res = self.session.post(self.url, data={ "skip": "true" })
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
        obj = json.loads(res.text)
        self.assertEquals(True, obj["success"])

        # Creation successful:
        res = self.session.get(self.url)
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
