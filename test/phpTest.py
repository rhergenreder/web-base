import unittest
import string
import random
import requests
import re
import json
import sys

class PhpTest(unittest.TestCase):

    def randomString(length):
       letters = string.ascii_lowercase + string.ascii_uppercase + string.digits
       return ''.join(random.choice(letters) for i in range(length))

    ADMIN_USERNAME = "Administrator"
    ADMIN_PASSWORD = randomString(16)

    def __init__(self, methods):
        super().__init__("test_methods")
        keywords = ["Fatal error", "Warning", "Notice", "Parse error", "Deprecated"]
        self.methods = methods
        self.phpPattern = re.compile("<b>(%s)</b>:" % ("|".join(keywords)))
        self.url = "http://localhost"
        self.session = requests.Session()

    def httpError(self, res):
        return "Server returned: %d %s" % (res.status_code, res.reason)

    def getPhpErrors(self, res):
        return [line for line in res.text.split("\n") if self.phpPattern.search(line)]

    def httpGet(self, target="/"):
        url = self.url + target
        res = self.session.get(url)
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
        return res

    def httpPost(self, target="/", data={}):
        url = self.url + target
        res = self.session.post(url, data=data)
        self.assertEquals(200, res.status_code, self.httpError(res))
        self.assertEquals([], self.getPhpErrors(res))
        obj = self.getJson(res)
        return obj

    def getJson(self, res):
        obj = None
        try:
            obj = json.loads(res.text)
        except:
            pass
        finally:
            self.assertTrue(isinstance(obj, dict), res.text)
            return obj

    def test_methods(self):
        print()
        print("Running Tests in %s" % self.__class__.__name__)
        for msg, method in self.methods.items():
            self.test_method(msg, method)

    def test_method(self, msg, method):
        sys.stdout.write("[ ] %s" % msg)
        method()
        sys.stdout.write("\r[+] %s\n" % msg)
        sys.stdout.flush()
