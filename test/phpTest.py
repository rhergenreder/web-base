import unittest
import string
import random
import re
import json

class PhpTest(unittest.TestCase):

    def randomString(length):
       letters = string.ascii_lowercase + string.ascii_uppercase + string.digits
       return ''.join(random.choice(letters) for i in range(length))

    ADMIN_USERNAME = "Administrator"
    ADMIN_PASSWORD = randomString(16)

    def __init__(self, test_method):
        super().__init__(test_method)
        keywords = ["Fatal error", "Warning", "Notice", "Parse error", "Deprecated"]
        self.phpPattern = re.compile("<b>(%s)</b>:" % ("|".join(keywords)))
        self.url = "http://localhost/"

    def httpError(self, res):
        return "Server returned: %d %s" % (res.status_code, res.reason)

    def getPhpErrors(self, res):
        return [line for line in res.text.split("\n") if self.phpPattern.search(line)]

    def getJson(self, res):
        obj = None
        try:
            obj = json.loads(res.text)
        except:
            pass
        finally:
            self.assertTrue(isinstance(obj, dict), res.text)
            return obj
