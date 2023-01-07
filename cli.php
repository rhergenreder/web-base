<?php

define('WEBROOT', realpath("."));

include_once 'Core/core.php';
require_once 'Core/datetime.php';
include_once 'Core/constants.php';

use Core\Configuration\DatabaseScript;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Condition\Compare;
use Core\Driver\SQL\Condition\CondIn;
use Core\Driver\SQL\Expression\DateSub;
use Core\Driver\SQL\SQL;
use Core\Objects\ConnectionData;

function printLine(string $line = "") {
  echo $line . PHP_EOL;
}

function _exit(string $line = "") {
  printLine($line);
  die();
}

function getDatabaseConfig(): ConnectionData {
  $configClass = "\\Site\\Configuration\\Database";
  $file = getClassPath($configClass);
  if (!file_exists($file) || !is_readable($file)) {
    _exit("Database configuration does not exist or is not readable");
  }

  include_once $file;
  return new $configClass();
}

$context = \Core\Objects\Context::instance();
if (!$context->isCLI()) {
  _exit("Can only be executed via CLI");
}

$database = $context->getConfig()->getDatabase();
if ($database !== null && $database->getProperty("isDocker", false) && !is_file("/.dockerenv")) {
  if (count($argv) < 3 || $argv[1] !== "db" || !in_array($argv[2], ["shell", "import", "export"])) {
    $command = array_merge(["docker", "exec", "-it", "php", "php"], $argv);
    $proc = proc_open($command, [1 => STDOUT, 2 => STDERR], $pipes, "/application");
    exit(proc_close($proc));
  }
}

function connectSQL(): ?SQL {
  global $context;
  $sql = $context->initSQL();
  if (!$sql || !$sql->isConnected()) {
    printLine("Could not establish database connection");
    return null;
  }

  return $sql;
}

function printHelp() {
  // TODO: help
}

function applyPatch(\Core\Driver\SQL\SQL $sql, string $patchName): bool {
  $class = str_replace('/', '\\', $patchName);
  $className = "\\Core\\Configuration\\$class";
  $classPath = getClassPath($className);
  if (!file_exists($classPath) || !is_readable($classPath)) {
    printLine("Database script file does not exist or is not readable");
    return false;
  }

  include_once $classPath;
  $obj = new $className();
  if (!($obj instanceof DatabaseScript)) {
    printLine("Not a database script");
    return false;
  }

  $queries = $obj->createQueries($sql);
  foreach ($queries as $query) {
    if (!$query->execute($sql)) {
      printLine($sql->getLastError());
      return false;
    }
  }

  return true;
}

function handleDatabase(array $argv) {
  $action = $argv[2] ?? "";

  if ($action === "migrate") {
    $sql = connectSQL() or die();

  } else if (in_array($action, ["export", "import", "shell"])) {

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
      } else if ($action === "shell") {
        $command_bin = "mysql";
        $descriptorSpec = [];
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
      } else if ($action === "shell") {
        $command_bin = "/usr/bin/psql";
        $descriptorSpec = [];
      }

    } else {
      _exit("Unsupported database type");
    }

    if ($database) {
      $command_args[] = $database;
    }

    $command = array_merge([$command_bin], $command_args);
    if ($config->getProperty("isDocker", false)) {
      $command = array_merge(["docker", "exec", "-it", $config->getHost()], $command);
    }

    $process = proc_open($command, $descriptorSpec, $pipes, null, $env);

    if (is_resource($process)) {
      if ($action === "import" && $inputData && count($pipes) > 0) {
        fwrite($pipes[0], $inputData);
        fclose($pipes[0]);
      }

      proc_close($process);
    }
  } else if ($action === "clean") {
    $sql = connectSQL() or die();
    printLine("Deleting user related data older than 90 days...");

    // 1st: Select all related tables and entities
    $tables = [];
    $res = $sql->select("entityId", "tableName")
      ->from("EntityLog")
      ->where(new Compare("modified", new DateSub($sql->now(), new Column("lifetime"), "DAY"), "<="))
      ->dump()
      ->execute();

    $success = ($res !== false);
    if (!$success) {
      _exit("Error querying data: " .  $sql->getLastError());
    }

    foreach ($res as $row) {
      $tableName = $row["tableName"];
      $uid = $row["entityId"];
      if (!isset($tables[$tableName])) {
        $tables[$tableName] = [];
      }
      $tables[$tableName][] = $uid;
    }

    // 2nd: delete!
    foreach ($tables as $table => $ids) {
      $success = $sql->delete($table)
        ->where(new CondIn(new Column("id"), $ids))
        ->execute();

      if (!$success) {
        printLine("Error deleting data: " .  $sql->getLastError());
      }
    }

    printLine("Done!");
  } else {
    _exit("Usage: cli.php db <migrate|import|export|shell> [options...]");
  }
}

function findPullBranch(array $output): ?string {
  foreach ($output as $line) {
    $parts = preg_split('/\s+/', $line);
    if (count($parts) >= 3 && $parts[2] === '(fetch)') {
      $remoteName = $parts[0];
      $url = $parts[1];
      if (endsWith($url, "@github.com:rhergenreder/web-base.git") ||
          endsWith($url, "@romanh.de:Projekte/web-base.git") ||
          $url === 'https://github.com/rhergenreder/web-base.git' ||
          $url === 'https://git.romanh.de/Projekte/web-base.git') {
        return "$remoteName/master";
      }
    }
  }

  return null;
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

    $oldPatchFiles = glob('Core/Configuration/Patch/*.php');
    printLine("$ git remote -v");
    exec("git remote -v", $gitRemote, $ret);
    if ($ret !== 0) {
      die();
    }

    $pullBranch = findPullBranch($gitRemote);
    if ($pullBranch === null) {
      $pullBranch = 'origin/master';
      printLine("Unable to find remote update branch. Make sure, you are still in a git repository, and one of the remote branches " .
                      "have the original fetch url");
      printLine("Trying to continue with '$pullBranch'");
    } else {
      printLine("Using remote update branch: $pullBranch");
    }

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
      printLine("Update could not be applied, check the git output.");
      printLine("Follow the instructions and afterwards turn off the maintenance mode again using:");
      printLine("cli.php maintenance off");
      printLine("Also don't forget to apply new database patches using: cli.php db migrate");
      die();
    }

    // TODO: also collect patches from Site/Configuration/Patch ... and what about database entities?
    $newPatchFiles = glob('Core/Configuration/Patch/*.php');
    $newPatchFiles = array_diff($newPatchFiles, $oldPatchFiles);
    if (count($newPatchFiles) > 0) {
      printLine("Applying new database patches");
      $sql = connectSQL();
      if ($sql) {
        foreach ($newPatchFiles as $patchFile) {
          if (preg_match("/Core\/Configuration\/(Patch\/.*)\.class\.php/", $patchFile, $match)) {
            $patchName = $match[1];
            applyPatch($sql, $patchName);
          }
        }
      }
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

function getConsoleWidth(): int {
  $width = getenv('COLUMNS');
  if (!$width) {
    $width = exec('tput cols');
    if (!$width) {
      $width = 80; // default gnome-terminal column count
    }
  }

  return intval($width);
}

function printTable(array $head, array $body) {

  $columns = [];
  foreach ($head as $key) {
    $columns[$key] = strlen($key);
  }

  $maxWidth = getConsoleWidth();
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
    $line = 0;
    foreach ($head as $key) {
      $width = min(max($maxWidth - $line, 0), $columns[$key]);
      $line += $width;
      echo str_pad($row[$key] ?? "", $width) . '   ';
    }
    printLine();
  }
}

function onSettings(array $argv) {
  global $context;
  connectSQL() or die();
  $action = $argv[2] ?? "list";

  if ($action === "list" || $action === "get") {
    $key = (($action === "list" || count($argv) < 4) ? null : $argv[3]);
    $req = new \Core\API\Settings\Get($context);
    $success = $req->execute(["key" => $key]);
    if (!$success) {
      _exit("Error listings settings: " . $req->getLastError());
    } else {
      $settings = [];
      foreach ($req->getResult()["settings"] as $key => $value) {
        $settings[] = ["key" => $key, "value" => $value];
      }
      printTable(["key", "value"], $settings);
    }
  } else if ($action === "set" || $action === "update") {
    if (count($argv) < 5) {
      _exit("Usage: $argv[0] settings $argv[2] <key> <value>");
    } else {
      $key = $argv[3];
      $value = $argv[4];
      $req = new \Core\API\Settings\Set($context);
      $success = $req->execute(["settings" => [$key => $value]]);
      if (!$success) {
        _exit("Error updating settings: " . $req->getLastError());
      }
    }
  } else if ($action === "unset" || $action === "delete") {
    if (count($argv) < 4) {
      _exit("Usage: $argv[0] settings $argv[2] <key>");
    } else {
      $key = $argv[3];
      $req = new \Core\API\Settings\Set($context);
      $success = $req->execute(["settings" => [$key => null]]);
      if (!$success) {
        _exit("Error updating settings: " . $req->getLastError());
      }
    }
  } else {
    _exit("Usage: $argv[0] settings <get|set|unset>");
  }
}

function onRoutes(array $argv) {
  global $context;
  connectSQL() or die();
  $action = $argv[2] ?? "list";

  if ($action === "list") {
    $req = new \Core\API\Routes\Fetch($context);
    $success = $req->execute();
    if (!$success) {
      _exit("Error fetching routes: " . $req->getLastError());
    } else {
      $routes = $req->getResult()["routes"];
      $head = ["id", "pattern", "type", "target", "extra", "active", "exact"];

      // strict boolean
      foreach ($routes as &$route) {
        $route["active"] = $route["active"] ? "true" : "false";
        $route["exact"] = $route["exact"] ? "true" : "false";
      }

      printTable($head, $routes);
    }
  } else if ($action === "add") {
    if (count($argv) < 7) {
      _exit("Usage: cli.php routes add <pattern> <type> <target> <exact> [extra]");
    }

    $params = array(
      "pattern" => $argv[3],
      "type" => $argv[4],
      "target" => $argv[5],
      "exact" => $argv[6],
      "extra" => $argv[7] ?? "",
    );

    $req  = new \Core\API\Routes\Add($context);
    $success = $req->execute($params);
    if (!$success) {
      _exit($req->getLastError());
    } else {
      _exit("Route added successfully");
    }
  } else if (in_array($action, ["remove","modify","enable","disable"])) {
    $routeId = $argv[3] ?? null;
    if ($routeId === null || ($action === "modify" && count($argv) < 8)) {
      if ($action === "modify") {
        _exit("Usage: cli.php routes $action <id> <pattern> <type> <target> <exact> [extra]");
      } else {
        _exit("Usage: cli.php routes $action <id>");
      }
    }

    $params = ["id" => $routeId];
    if ($action === "remove") {
      $input = null;
      do {
        if ($input === "n") {
          die();
        }
        echo "Remove route #$routeId? (y|n): ";
      } while(($input = trim(fgets(STDIN))) !== "y");

      $req = new \Core\API\Routes\Remove($context);
    } else if ($action === "enable") {
      $req = new \Core\API\Routes\Enable($context);
    } else if ($action === "disable") {
      $req = new \Core\API\Routes\Disable($context);
    } else if ($action === "modify") {
      $req = new \Core\API\Routes\Update($context);
      $params["pattern"] = $argv[4];
      $params["type"] = $argv[5];
      $params["target"] = $argv[6];
      $params["exact"] = $argv[7];
      $params["extra"] = $argv[8] ?? "";
    } else {
      _exit("Unsupported action");
    }

    $success = $req->execute($params);
    if (!$success) {
      _exit($req->getLastError());
    } else {
      _exit("Route updated successfully");
    }
  } else {
    _exit("Usage: cli.php routes <list|enable|disable|add|remove|modify> [options...]");
  }
}

function onTest($argv) {
  $files = glob(WEBROOT . '/test/*.test.php');
  $requestedTests = array_filter(array_slice($argv, 2), function ($t) {
    return !startsWith($t, "-");
  });
  $verbose = in_array("-v", $argv);

  foreach ($files as $file) {
    include_once $file;
    $baseName = substr(basename($file), 0, - strlen(".test.php"));
    if (!empty($requestedTests) && !in_array($baseName, $requestedTests)) {
      continue;
    }

    $className =  $baseName . "Test";
    if (class_exists($className)) {
      echo "=== Running $className ===" . PHP_EOL;
      $testClass = new \PHPUnit\Framework\TestSuite();
      $testClass->addTestSuite($className);
      $result = $testClass->run();
      echo "Done after " . $result->time() . "s" . PHP_EOL;
      $stats = [
        "total" => $result->count(),
        "skipped" => $result->skippedCount(),
        "error" => $result->errorCount(),
        "failure" => $result->failureCount(),
        "warning" => $result->warningCount(),
      ];

      // Summary
      echo implode(", ", array_map(function ($key) use ($stats) {
          return "$key: " . $stats[$key];
        }, array_keys($stats))) . PHP_EOL;

      $reports = array_merge($result->errors(), $result->failures());
      foreach ($reports as $error) {
        $exception = $error->thrownException();
        echo $error->toString();
        if ($verbose) {
          echo ". Stacktrace:" . PHP_EOL . $exception->getTraceAsString() . PHP_EOL;
        } else {
          $location = array_filter($exception->getTrace(), function ($t) use ($file) {
            return isset($t["file"]) && $t["file"] === $file;
          });
          $location = array_reverse($location);
          $location = array_pop($location);
          if ($location)  {
            echo " in " . substr($location["file"], strlen(WEBROOT)) . "#" . $location["line"] . PHP_EOL;
          } else {
            echo PHP_EOL;
          }
        }
      }
    }
  }
}

function onMail($argv) {
  global $context;
  $action = $argv[2] ?? null;
  if ($action === "send_queue") {
    connectSQL() or die();
    $req = new \Core\API\Mail\SendQueue($context);
    $debug = in_array("debug", $argv);
    if (!$req->execute(["debug" => $debug])) {
      _exit("Error processing mail queue: " . $req->getLastError());
    }
  } else {
    _exit("Usage: cli.php mail <send_queue> [options...]");
  }
}

function onImpersonate($argv) {
  global $context;

  if (count($argv) < 3) {
    _exit("Usage: cli.php impersonate <user_id|user_name>");
  }

  $sql = connectSQL() or die();

  $userId = $argv[2];
  if (!is_numeric($userId)) {
    $res = $sql->select("id")
      ->from("User")
      ->whereEq("name", $userId)
      ->execute();
    if ($res === false) {
      _exit("SQL error: " . $sql->getLastError());
    } else {
      $userId = $res[0]["id"];
    }
  }

  $user = new \Core\Objects\DatabaseEntity\User($userId);
  $session = new \Core\Objects\DatabaseEntity\Session($context, $user);
  $session->setData(["2faAuthenticated" => true]);
  $session->update();
  echo "session=" . $session->getCookie() . PHP_EOL;
}

$argv = $_SERVER['argv'];
if (count($argv) < 2) {
  _exit("Usage: cli.php <db|routes|settings|maintenance|impersonate> [options...]");
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
  case 'test':
    onTest($argv);
    break;
  case 'mail':
    onMail($argv);
    break;
  case 'settings':
    onSettings($argv);
    break;
  case 'impersonate':
    onImpersonate($argv);
    break;
  default:
    printLine("Unknown command '$command'");
    printLine();
    printHelp();
    exit;
}