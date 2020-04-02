## Web-Base Test Suite

### Introduction

This script performs database and API tests on a clean local environment. It assumes, the web-base is running on http://localhost/. The test tool can either
use an existing database or create a temporary database (recommended).

### Usage

To use this tool, some requirements must be installed. This can be done using: `pip install -r requirements.txt`

```
usage: test.py [-h] [--username USERNAME] [--password PASSWORD] [--host HOST]
               [--port PORT] [--database DATABASE] [--force]
               DBMS

Web-Base database test suite

positional arguments:
  DBMS                  the dbms to setup, must be one of: mysql, postgres,
                        oracle

optional arguments:
  -h, --help            show this help message and exit
  --username USERNAME, -u USERNAME
                        the username used for connecting to the dbms, default:
                        root
  --password PASSWORD, -p PASSWORD
                        the password used for connecting to the dbms, default:
                        (empty)
  --host HOST, -H HOST  the host where the dbms is running on, default:
                        localhost
  --port PORT, -P PORT  the port where the dbms is running on, default:
                        (depends on dbms)
  --database DATABASE, -d DATABASE
                        the name of the database for the test suite, default:
                        randomly chosen and created
  --force               Delete existing configuration files
```
