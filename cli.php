<?php

include_once 'core/core.php';
include_once 'core/constants.php';

use Configuration\Configuration;
use Configuration\DatabaseScript;
use Objects\ConnectionData;
use Objects\User;

function printLine(string $line = "") {
  echo $line . PHP_EOL;
}

function _exit(string $line = "") {
  printLine($line);
  die();
}

if (php_sapi_name() !== "cli") {
  _exit("Can only be executed via CLI");
}

function getDatabaseConfig(): ConnectionData {
  $configClass = "\\Configuration\\Database";
  $file = getClassPath($configClass);
  if (!file_exists($file) || !is_readable($file)) {
    _exit("Database configuration does not exist or is not readable");
  }

  include_once $file;
  return new $configClass();
}

function getUser(): User {
  $config = new Configuration();
  $user = new User($config);
  if (!$user->getSQL() || !$user->getSQL()->isConnected()) {
    _exit("Could not establish database connection");
  }

  return $user;
}

function printHelp() {
  // TODO: help
}

function handleDatabase(array $argv) {
  $action = $argv[2] ?? "";

  if ($action === "migrate") {
    $class = $argv[3] ?? null;
    if (!$class) {
      _exit("Usage: cli.php db migrate <class name>");
    }

    $class = str_replace('/', '\\', $class);
    $className = "\\Configuration\\$class";
    $classPath = getClassPath($className);
    if (!file_exists($classPath) || !is_readable($classPath)) {
      _exit("Database script file does not exist or is not readable");
    }

    include_once $classPath;
    $obj = new $className();
    if (!($obj instanceof DatabaseScript)) {
      _exit("Not a database script");
    }

    $user = getUser();
    $sql = $user->getSQL();
    $queries = $obj->createQueries($sql);
    foreach ($queries as $query) {
      if (!$query->execute($sql)) {
        _exit($sql->getLastError());
      }
    }
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
        _exit("Usage: cli.php db import <path>");
      }

      if (!file_exists($file) || !is_readable($file)) {
        _exit("File not found or not readable");
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
      }

    } else {
      _exit("Unsupported database type");
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
    _exit("Usage: cli.php db <migrate|import|export> [options...]");
  }
}

function onMaintenance(array $argv) {
  $action = $argv[2] ?? "status";
  $maintenanceFile = "MAINTENANCE";
  $isMaintenanceEnabled = file_exists($maintenanceFile);

  if ($action === "status") {
    _exit("Maintenance: " . ($isMaintenanceEnabled ? "on" : "off"));
  } else if ($action === "on") {
    $file = fopen($maintenanceFile, 'w') or _exit("Unable to create maintenance file");
    fclose($file);
    _exit("Maintenance enabled");
  } else if ($action === "off") {
    if (file_exists($maintenanceFile)) {
      if (!unlink($maintenanceFile)) {
        _exit("Unable to delete maintenance file");
      }
    }
    _exit("Maintenance disabled");
  } else if ($action === "update") {

    // TODO: find that dynamically
    $pullBranch = "root/master";
    $pushBranch = "origin/master";

    printLine("$ git fetch " . str_replace("/", " ", $pullBranch));
    exec("git fetch " . str_replace("/", " ", $pullBranch), $gitFetch, $ret);
    if ($ret !== 0) {
      die();
    }

    printLine("$ git log HEAD..$pullBranch --oneline");
    exec("git log HEAD..$pullBranch --oneline", $gitLog, $ret);
    if ($ret !== 0) {
      die();
    } else if (count($gitLog) === 0) {
      _exit("Already up to date.");
    }

    printLine("Found updates, checking repository state");
    printLine("$ git diff-index --quiet HEAD --"); // check for any uncommitted changes
    exec("git diff-index --quiet HEAD --", $gitDiff, $ret);
    if ($ret !== 0) {
      _exit("You have uncommitted changes. Please commit them before updating.");
    }

    printLine("$ git rev-list HEAD...$pushBranch --ignore-submodules --count");
    exec("git rev-list HEAD...$pushBranch --ignore-submodules --count", $gitRevList, $ret);
    if ($ret !== 0) {
      _exit("HEAD is behind your local branch. Please push your commits before updating.");
    }

    // enable maintenance mode if it wasn't turned on before
    if (!$isMaintenanceEnabled) {
      printLine("Turning on maintenance mode");
      $file = fopen($maintenanceFile, 'w') or _exit("Unable to create maintenance file");
      fclose($file);
    }

    printLine("Ready to update, pulling and merging");
    printLine("$ git pull " . str_replace("/", " ", $pullBranch) . " --no-ff");
    exec("git pull " . str_replace("/", " ", $pullBranch) . " --no-ff", $gitPull, $ret);
    if ($ret !== 0) {
      printLine();
      printLine("Update could not be applied, check the following git message.");
      printLine("Follow the instructions and afterwards turn off the maintenance mode again using:");
      printLine("cli.php maintenance off");
      die();
    }

    // disable maintenance mode again
    if (!$isMaintenanceEnabled) {
      printLine("Turning off maintenance mode");
      if (file_exists($maintenanceFile)) {
        if (!unlink($maintenanceFile)) {
          _exit("Unable to delete maintenance file");
        }
      }
    }
  } else {
    _exit("Usage: cli.php maintenance <status|on|off|update>");
  }
}

function printTable(array $head, array $body) {

  $columns = [];
  foreach ($head as $key) {
    $columns[$key] = strlen($key);
  }

  foreach ($body as $row) {
    foreach ($head as $key) {
      $value = $row[$key] ?? "";
      $length = strlen($value);
      $columns[$key] = max($columns[$key], $length);
    }
  }

  // print table
  foreach ($head as $key) {
    echo str_pad($key, $columns[$key]) . '   ';
  }
  printLine();

  foreach ($body as $row) {
    foreach ($head as $key) {
      echo str_pad($row[$key] ?? "", $columns[$key]) . '   ';
    }
    printLine();
  }
}

// TODO: add missing api functions (should be all internal only i guess)
function onRoutes(array $argv) {

  $user = getUser();
  $action = $argv[2] ?? "list";

  if ($action === "list") {
    $req = new Api\Routes\Fetch($user);
    $success = $req->execute();
    if (!$success) {
      _exit("Error fetching routes: " . $req->getLastError());
    } else {
      $routes = $req->getResult()["routes"];
      $head = ["uid", "request", "action", "target", "extra", "active"];

      // strict boolean
      foreach ($routes as &$route) {
        $route["active"] = $route["active"] ? "true" : "false";
      }

      printTable($head, $routes);
    }
  } else if ($action === "add") {
    if (count($argv) < 6) {
      _exit("Usage: cli.php routes add <request> <action> <target> [extra]");
    }

    $params = array(
      "request" => $argv[3],
      "action" => $argv[4],
      "target" => $argv[5],
      "extra" => $argv[6] ?? ""
    );

    /*
    $req  = new Api\Routes\Add($user);
    $success = $req->execute($params);
    if (!$success) {
      _exit($req->getLastError());
    } else {
      _exit("Route added successfully");
    }*/
  } else if (in_array($action, ["remove","modify","enable","disable"])) {
    $uid = $argv[3] ?? null;
    if ($uid === null || ($action === "modify" && count($argv) < 7)) {
      if ($action === "modify") {
        _exit("Usage: cli.php routes $action <uid> <request> <action> <target> [extra]");
      } else {
        _exit("Usage: cli.php routes $action <uid>");
      }
    }

    $params = ["uid" => $uid];
    if ($action === "remove") {
      $input = null;
      do {
        if ($input === "n") {
          die();
        }
        echo "Remove route #$uid? (y|n): ";
      } while(($input = trim(fgets(STDIN))) !== "y");

      // $req = new Api\Routes\Remove($user);
    } else if ($action === "enable") {
      // $req = new Api\Routes\Enable($user);
    } else if ($action === "disable") {
      // $req = new Api\Routes\Disable($user);
    } else if ($action === "modify") {
      // $req = new Api\Routes\Update($user);
      $params["request"] = $argv[4];
      $params["action"] = $argv[5];
      $params["target"] = $argv[6];
      $params["extra"] = $argv[7] ?? "";
    } else {
      _exit("Unsupported action");
    }

    /*
    $success = $req->execute($params);
    if (!$success) {
      _exit($req->getLastError());
    } else {
      _exit("Route updated successfully");
    }
    */
  } else {
    _exit("Usage: cli.php routes <list|enable|disable|add|remove|modify> [options...]");
  }
}

$argv = $_SERVER['argv'];
if (count($argv) < 2) {
  _exit("Usage: cli.php <db|routes|settings|maintenance> [options...]");
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
    onRoutes($argv);
    break;
  case 'maintenance':
    onMaintenance($argv);
    break;
  default:
    printLine("Unknown command '$command'");
    printLine();
    printHelp();
    exit;
}