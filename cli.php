<?php

include_once 'core/core.php';
include_once 'core/constants.php';

use Configuration\DatabaseScript;
use Driver\SQL\SQL;
use Objects\ConnectionData;

if (php_sapi_name() !== "cli") {
  die();
}

function getDatabaseConfig(): ConnectionData {
  $configClass = "\\Configuration\\Database";
  $file = getClassPath($configClass);
  if (!file_exists($file) || !is_readable($file)) {
    die("Database configuration does not exist or is not readable\n");
  }

  include_once $file;
  return new $configClass();
}

function connectDatabase() {
  $config = getDatabaseConfig();
  $db = SQL::createConnection($config);
  if (!($db instanceof SQL) || !$db->isConnected()) {
    if ($db instanceof SQL) {
      die($db->getLastError() . "\n");
    } else {
      $msg = (is_string($db) ? $db : "Unknown Error");
      die("Database error: $msg\n");
    }
  }

  return $db;
}

function printHelp() {

}

function handleDatabase($argv) {
  $action = $argv[2] ?? "";

  switch ($action) {
    case 'migrate':
      $class = $argv[3] ?? null;
      if (!$class) {
        die("Usage: cli.php db migrate <class name>\n");
      }

      $class = str_replace('/', '\\', $class);
      $className = "\\Configuration\\$class";
      $classPath = getClassPath($className);
      if (!file_exists($classPath) || !is_readable($classPath)) {
        die("Database script file does not exist or is not readable\n");
      }

      include_once $classPath;
      $obj = new $className();
      if (!($obj instanceof DatabaseScript)) {
        die("Not a database script\n");
      }

      $db = connectDatabase();
      $queries = $obj->createQueries($db);
      foreach ($queries as $query) {
        if (!$query->execute($db)) {
          die($db->getLastError());
        }
      }

      $db->close();
      break;
    case 'export':
      $config = getDatabaseConfig();
      $dbType = $config->getProperty("type") ?? null;
      $user = $config->getLogin();
      $password = $config->getPassword();
      $database = $config->getProperty("database");
      $host = $config->getHost();
      $port = $config->getPort();

      $env = [];
      $output = $argv[3] ?? null;
      $descriptorSpec = [STDIN, STDOUT, STDOUT];

      if ($dbType === "mysql") {

        $command = ["mysqldump", "-u", $user, '-h', $host, '-P', $port, "--password=$password"];
        if ($database) {
          $command[] = $database;
        }

      } else if ($dbType === "postgres") {
        $command = ["pg_dump", "-U", $user, '-h', $host, '-p', $port];
        if ($database) {
          $command[] = $database;
        }

        $env["PGPASSWORD"] = $password;
      } else {
        die("Unsupported database type\n");
      }

      if ($output) {
        $descriptorSpec[1] = ["file", $output, "w"];
      }

      $process = proc_open($command, $descriptorSpec, $pipes, null, $env);
      proc_close($process);
      break;
    default:
      die("Usage: cli.php db <import|export|migrate>\n");
  }
}

$argv = $_SERVER['argv'];
if (count($argv) < 2) {
  die("Usage: cli.php <db|routes|settings|maintenance> [options...]\n");
}

$command = $argv[1];
switch ($command) {
  case 'help':
    printHelp();
    exit;
  case 'db':
    handleDatabase($argv);
    break;
  case 'routes':
    break;
  default:
    echo "Unknown command '$command'\n\n";
    printHelp();
    exit;
}