from phpTest import PhpTest

class ApiTestCase(PhpTest):

    def __init__(self):
        super().__init__({
            "Testing login…": self.test_login,
            "Testing already logged in…": self.test_already_logged_in,

            # ApiKeys
            "Testing get api keys empty…": self.test_get_api_keys_empty,
            "Testing create api key…": self.test_create_api_key,
            "Testing refresh api key…": self.test_refresh_api_key,
            "Testing revoke api key…": self.test_revoke_api_key,

            # Notifications
            "Testing fetch notifications…": self.test_fetch_notifications,

            "Testing logout…": self.test_logout,
        })

    def api(self, method):
        return "/api/%s" % method

    def getApiKeys(self):
        obj = self.httpPost(self.api("apiKey/fetch"))
        self.assertEquals(True, obj["success"], obj["msg"])
        return obj

    def test_login(self):
        obj = self.httpPost(self.api("user/login"), data={ "username": PhpTest.ADMIN_USERNAME, "password": PhpTest.ADMIN_PASSWORD })
        self.assertEquals(True, obj["success"], obj["msg"])
        return obj

    def test_already_logged_in(self):
        obj = self.test_login()
        self.assertEquals("You are already logged in", obj["msg"])

    def test_get_api_keys_empty(self):
        obj = self.getApiKeys()
        self.assertEquals([], obj["api_keys"])

    def test_create_api_key(self):
        obj = self.httpPost(self.api("apiKey/create"))
        self.assertEquals(True, obj["success"], obj["msg"])
        self.assertTrue("api_key" in obj)
        self.apiKey = obj["api_key"]

        obj = self.getApiKeys()
        self.assertEquals(1, len(obj["api_keys"]))
        self.assertDictEqual(self.apiKey, obj["api_keys"][0])

    def test_refresh_api_key(self):
        obj = self.httpPost(self.api("apiKey/refresh"), data={"id": self.apiKey["uid"]})
        self.assertEquals(True, obj["success"], obj["msg"])
        self.assertTrue("valid_until" in obj)
        self.assertTrue(obj["valid_until"] >= self.apiKey["valid_until"])

    def test_revoke_api_key(self):
        obj = self.httpPost(self.api("apiKey/revoke"), data={"id": self.apiKey["uid"]})
        self.assertEquals(True, obj["success"], obj["msg"])
        self.test_get_api_keys_empty()

    def test_fetch_notifications(self):
        obj = self.httpPost(self.api("notifications/fetch"))
        self.assertEquals(True, obj["success"], obj["msg"])

    def test_logout(self):
        obj = self.httpPost(self.api("user/logout"))
        self.assertEquals(True, obj["success"], obj["msg"])
        obj = self.httpPost(self.api("user/logout"))
        self.assertEquals(False, obj["success"])
