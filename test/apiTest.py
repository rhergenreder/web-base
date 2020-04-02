from phpTest import PhpTest

class ApiTestCase(PhpTest):

    def __init__(self):
        super().__init__({
            "Testing login…": self.test_login,
            "Testing already logged in…": self.test_already_logged_in,
            "Testing get api keys empty…": self.test_get_api_keys,
        })

    def api(self, method):
        return "/api/%s" % method

    def test_login(self):
        obj = self.httpPost(self.api("login"), data={ "username": PhpTest.ADMIN_USERNAME, "password": PhpTest.ADMIN_PASSWORD })
        self.assertEquals(True, obj["success"], obj["msg"])
        return obj

    def test_already_logged_in(self):
        obj = self.test_login()
        self.assertEquals("You are already logged in", obj["msg"])

    def test_get_api_keys(self):
        obj = self.httpPost(self.api("getApiKeys"))
        self.assertEquals(True, obj["success"], obj["msg"])
        self.assertEquals([], obj["api_keys"])
        return obj
