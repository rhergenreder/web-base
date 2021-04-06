<?php

include_once 'core/core.php';

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
      $db = connectDatabase();
      break;
    case 'dump':
      $config = getDatabaseConfig();
      $output = $argv[3] ?? null;
      $user = $config->getLogin();
      $password = $config->getPassword();
      $database = $config->getProperty("database");
      $command = ["mysqldump", "-u", $user, "--password=$password"];
      $descriptorSpec = [STDIN, STDOUT, STDOUT];

      if ($database) {
        $command[] = $database;
      }

      if ($output) {
        $descriptorSpec[1] = ["file", $output, "w"];
      }

      $process = proc_open($command, $descriptorSpec, $pipes);
      proc_close($process);
      break;
    default:
      die("Usage: cli.php db <dump|migrate>\n");
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