import requests

from phpTest import PhpTest

class InstallTestCase(PhpTest):

    def __init__(self, args):
        super().__init__("test_install")
        self.args = args
        self.session = requests.Session()

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

        # 1. Invalid username
        for username in ["a", "a"*33]:
            res = self.session.post(self.url, data={ "username": username, "password": "123456", "confirmPassword": "123456" })
            self.assertEquals(200, res.status_code, self.httpError(res))
            self.assertEquals([], self.getPhpErrors(res))
            obj = self.getJson(res)
            self.assertEquals(False, obj["success"])
            self.assertEquals("The username should be between 5 and 32 characters long", obj["msg"])

        # 2. Invalid password
        res = self.session.post(self.url, data={ "username": PhpTest.ADMIN_USERNAME, "password": "1", "confirmPassword": "1" })
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
        obj = self.getJson(res)
        self.assertEquals(False, obj["success"])
        self.assertEquals("The password should be at least 6 characters long", obj["msg"])

        # 3. Passwords do not match
        res = self.session.post(self.url, data={ "username": PhpTest.ADMIN_USERNAME, "password": "1", "confirmPassword": "2" })
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
        obj = self.getJson(res)
        self.assertEquals(False, obj["success"])
        self.assertEquals("The given passwords do not match", obj["msg"])

        # 4. User creation OK
        res = self.session.post(self.url, data={ "username": PhpTest.ADMIN_USERNAME, "password": PhpTest.ADMIN_PASSWORD, "confirmPassword": PhpTest.ADMIN_PASSWORD })
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
        obj = self.getJson(res)
        self.assertEquals(True, obj["success"])

        # Mail: SKIP
        res = self.session.post(self.url, data={ "skip": "true" })
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
        obj = self.getJson(res)
        self.assertEquals(True, obj["success"])

        # Creation successful:
        res = self.session.get(self.url)
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
