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
  // TODO: help
}

function handleDatabase($argv) {
  $action = $argv[2] ?? "";

  if ($action === "migrate") {
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
  } else if ($action === "export" || $action === "import") {

    // database config
    $config = getDatabaseConfig();
    $dbType = $config->getProperty("type") ?? null;
    $user = $config->getLogin();
    $password = $config->getPassword();
    $database = $config->getProperty("database");
    $host = $config->getHost();
    $port = $config->getPort();

    // subprocess config
    $env = [];
    $options = array_slice($argv, 3);
    $dataOnly = in_array("--data-only", $options) || in_array("-d", $options);
    $descriptorSpec = [STDIN, STDOUT, STDOUT];
    $inputData = null;

    // argument config
    if ($action === "import") {
      $file = $argv[3] ?? null;
      if (!$file) {
        die("Usage: cli.php db import <path>\n");
      }

      if (!file_exists($file) || !is_readable($file)) {
        die("File not found or not readable\n");
      }

      $inputData = file_get_contents($file);
    }

    if ($dbType === "mysql") {
      $command_args = ["-u", $user, '-h', $host, '-P', $port, "--password=$password"];
      if ($action === "export") {
        $command_bin = "mysqldump";
        if ($dataOnly) {
          $command_args[] = "--skip-triggers";
          $command_args[] = "--compact";
          $command_args[] = "--no-create-info";
        }
      } else if ($action === "import") {
        $command_bin = "mysql";
        $descriptorSpec[0] = ["pipe", "r"];
      } else {
        die("Unsupported action\n");
      }
    } else if ($dbType === "postgres") {

      $env["PGPASSWORD"] = $password;
      $command_args = ["-U", $user, '-h', $host, '-p', $port];

      if ($action === "export") {
        $command_bin = "/usr/bin/pg_dump";
        if ($dataOnly) {
          $command_args[] = "--data-only";
        }
      } else if ($action === "import") {
        $command_bin = "/usr/bin/psql";
        $descriptorSpec[0] = ["pipe", "r"];
      } else {
        die("Unsupported action\n");
      }

    } else {
      die("Unsupported database type\n");
    }

    if ($database) {
      $command_args[] = $database;
    }

    $command = array_merge([$command_bin], $command_args);
    $process = proc_open($command, $descriptorSpec, $pipes, null, $env);

    if (is_resource($process)) {
      if ($action === "import" && $inputData && count($pipes) > 0) {
        fwrite($pipes[0], $inputData);
        fclose($pipes[0]);
      }

      proc_close($process);
    }
  } else {
    die("Usage: cli.php db <migrate|import|export> [options...]");
  }
}

function onMaintenance($argv) {
  $action = $argv[2] ?? "status";
  $maintenanceFile = "MAINTENANCE";
  $isMaintenanceEnabled = file_exists($maintenanceFile);

  if ($action === "status") {
    die("Maintenance: " . ($isMaintenanceEnabled ? "on" : "off") . "\n");
  } else if ($action === "on") {
    $file = fopen($maintenanceFile, 'w') or die("Unable to create maintenance file\n");
    fclose($file);
    die("Maintenance enabled\n");
  } else if ($action === "off") {
    if (file_exists($maintenanceFile)) {
      if (!unlink($maintenanceFile)) {
        die("Unable to delete maintenance file\n");
      }
    }
    die("Maintenance disabled\n");
  } else {
    die("Usage: cli.php maintenance <status|on|off>\n");
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
    // TODO: routes
    break;
  case 'maintenance':
    onMaintenance($argv);
    break;
  default:
    echo "Unknown command '$command'\n\n";
    printHelp();
    exit;
}