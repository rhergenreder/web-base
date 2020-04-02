#!/usr/bin/env python3

import os
import sys
import argparse
import random
import string
import unittest
import mysql.connector

from installTest import InstallTestCase

CONFIG_FILES = ["../core/Configuration/Database.class.php","../core/Configuration/JWT.class.php","../core/Configuration/Mail.class.php"]

def randomName(length):
   letters = string.ascii_lowercase
   return ''.join(random.choice(letters) for i in range(length))

def performTest(args):
    suite = unittest.TestSuite()
    suite.addTest(InstallTestCase(args))
    runner = unittest.TextTestRunner()
    runner.run(suite)

def testMysql(args):

    # Create a temporary database
    cursor = None
    database = None
    connection = None
    if args.database is None:
        args.database = "webbase_test_%s" % randomName(6)
        config = {
            "host": args.host,
            "user": args.username,
            "passwd": args.password,
            "port": args.port
        }

        print("[ ] Connecting to dbms…")
        connection = mysql.connector.connect(**config)
        print("[+] Success")
        cursor = connection.cursor()
        print("[ ] Creating temporary databse %s" % args.database)
        cursor.execute("CREATE DATABASE %s" % args.database)
        print("[+] Success")

    # perform test
    try:
        args.type = "mysql"
        performTest(args)
    finally:
        if cursor is not None:
            print("[ ] Deleting temporary database")
            cursor.execute("DROP DATABASE %s" % args.database)
            cursor.close()
            print("[+] Success")

    if connection is not None:
        print("[ ] Closing connection…")
        connection.close()

if __name__ == "__main__":

    supportedDbms = {
        "mysql": 3306,
        "postgres": 5432,
        "oracle": 1521
    }

    parser = argparse.ArgumentParser(description='Web-Base database test suite')
    parser.add_argument('dbms', metavar='DBMS', type=str, help='the dbms to setup, must be one of: %s' % ", ".join(supportedDbms.keys()))
    parser.add_argument('--username', '-u', type=str, help='the username used for connecting to the dbms, default: root', default='root')
    parser.add_argument('--password', '-p', type=str, help='the password used for connecting to the dbms, default: (empty)', default='')
    parser.add_argument('--host', '-H', type=str, help='the host where the dbms is running on, default: localhost', default='localhost')
    parser.add_argument('--port', '-P', type=int, help='the port where the dbms is running on, default: (depends on dbms)')
    parser.add_argument('--database', '-d', type=str, help='the name of the database for the test suite, default: randomly chosen and created')
    parser.add_argument('--force', action='store_const', help='Delete existing configuration files', const=True)

    args = parser.parse_args()
    if args.dbms not in supportedDbms:
        print("Unsupported dbms. Supported values: %s" % ", ".join(supportedDbms.keys()))
        exit(1)

    for f in CONFIG_FILES:
        if os.path.isfile(f):
            if not args.force:
                print("File %s exists. The testsuite is required to perform tests on a clean environment. Specify --force to delete those files" % f)
                exit(1)
            else:
                os.remove(f)

    if args.port is None:
        args.port = supportedDbms[args.dbms]

    if args.dbms == "mysql":
        testMysql(args)
