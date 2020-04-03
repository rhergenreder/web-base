from phpTest import PhpTest

import sys

class InstallTestCase(PhpTest):

    def __init__(self, args):
        super().__init__({
            "Testing connection…": self.test_connection,
            "Testing database setup…": self.test_database_setup,
            "Testing invalid usernames…": self.test_invalid_usernames,
            "Testing invalid password…": self.test_invalid_password,
            "Testing not matching password…": self.test_not_matching_passwords,
            "Testing invalid email…": self.test_invalid_email,
            "Testing user creation…": self.test_create_user,
            "Testing skip mail configuration…": self.test_skil_mail_config,
            "Testing complete setup…": self.test_complete_setup,
        })
        self.args = args

    def test_connection(self):
        self.httpGet()

    def test_database_setup(self):
        obj = self.httpPost(data=vars(self.args))
        self.assertEquals(True, obj["success"], obj["msg"])

    def test_invalid_usernames(self):
        for username in ["a", "a"*33]:
            obj = self.httpPost(data={ "username": username, "password": "123456", "confirmPassword": "123456" })
            self.assertEquals(False, obj["success"])
            self.assertEquals("The username should be between 5 and 32 characters long", obj["msg"])

    def test_invalid_password(self):
        obj = self.httpPost(data={ "username": PhpTest.ADMIN_USERNAME, "password": "1", "confirmPassword": "1" })
        self.assertEquals(False, obj["success"])
        self.assertEquals("The password should be at least 6 characters long", obj["msg"])

    def test_not_matching_passwords(self):
        obj = self.httpPost(data={ "username": PhpTest.ADMIN_USERNAME, "password": "1", "confirmPassword": "2" })
        self.assertEquals(False, obj["success"])
        self.assertEquals("The given passwords do not match", obj["msg"])

    def test_invalid_email(self):
        obj = self.httpPost(data={ "username": PhpTest.ADMIN_USERNAME, "password": PhpTest.ADMIN_PASSWORD, "confirmPassword": PhpTest.ADMIN_PASSWORD, "email": "123abc" })
        self.assertEquals(False, obj["success"])
        self.assertEquals("Invalid email address", obj["msg"])

    def test_create_user(self):
        obj = self.httpPost(data={ "username": PhpTest.ADMIN_USERNAME, "password": PhpTest.ADMIN_PASSWORD, "confirmPassword": PhpTest.ADMIN_PASSWORD, "email": "test@test.com" })
        self.assertEquals(True, obj["success"], obj["msg"])

    def test_skil_mail_config(self):
        obj = self.httpPost(data={ "skip": "true" })
        self.assertEquals(True, obj["success"], obj["msg"])

    def test_complete_setup(self):
        res = self.httpGet()
        self.assertTrue("Installation finished" in res.text)
