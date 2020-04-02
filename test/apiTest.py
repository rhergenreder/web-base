import requests
import json

from phpTest import PhpTest

class ApiTestCase(PhpTest):

    def __init__(self):
        super().__init__("test_api")
        self.session = requests.Session()

    def api(self, method):
        return "%s/api/%s" % (self.url, method)

    def test_api(self):

        res = self.session.post(self.api("login"), data={ "username": PhpTest.ADMIN_USERNAME, "password": PhpTest.ADMIN_PASSWORD })
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
        obj = json.loads(res.text)
        self.assertEquals(True, obj["success"], obj["msg"])
