<?php

define('WEBROOT', realpath("."));

include_once 'core/core.php';
require_once 'core/datetime.php';
include_once 'core/constants.php';

use Configuration\Configuration;
use Configuration\DatabaseScript;
use Driver\SQL\Column\Column;
use Driver\SQL\Condition\Compare;
use Driver\SQL\Condition\CondIn;
use Driver\SQL\Expression\DateSub;
use Objects\ConnectionData;
use Objects\User;

function printLine(string $line = "") {
  echo $line . PHP_EOL;
}

function _exit(string $line = "") {
  printLine($line);
  die();
}

if (!is_cli()) {
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

$config = new Configuration();
$database = $config->getDatabase();
if ($database !== null && $database->getProperty("isDocker", false) && !is_file("/.dockerenv")) {
  if (count($argv) < 2 || $argv[1] !== "db") {
    $command = array_merge(["docker", "exec", "-it", "php", "php"], $argv);
    $proc = proc_open($command, [1 => STDOUT, 2 => STDERR], $pipes, "/application");
    exit(proc_close($proc));
  }
}

function getUser(): ?User {
  global $config;
  $user = new User($config);
  if (!$user->getSQL() || !$user->getSQL()->isConnected()) {
    printLine("Could not establish database connection");
    return null;
  }

  return $user;
}

function printHelp() {
  // TODO: help
}

function applyPatch(\Driver\SQL\SQL $sql, string $patchName): bool {
  $class = str_replace('/', '\\', $patchName);
  $className = "\\Configuration\\$class";
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
    $class = $argv[3] ?? null;
    if (!$class) {
      _exit("Usage: cli.php db migrate <class name>");
    }

    $user = getUser() or die();
    $sql = $user->getSQL();
    applyPatch($sql, $class);
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
    if ($config->getProperty("isDocker", false)) {
      $command =  array_merge(["docker", "exec", "-it", "db"], $command);
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
    $user = getUser() or die();
    $sql = $user->getSQL();

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
    foreach ($tables as $table => $uids) {
      $success = $sql->delete($table)
        ->where(new CondIn(new Column("uid"), $uids))
        ->execute();

      if (!$success) {
        printLine("Error deleting data: " .  $sql->getLastError());
      }
    }

    printLine("Done!");
  } else {
    _exit("Usage: cli.php db <migrate|import|export> [options...]");
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

    $oldPatchFiles = glob('core/Configuration/Patch/*.php');
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

    $newPatchFiles = glob('core/Configuration/Patch/*.php');
    $newPatchFiles = array_diff($newPatchFiles, $oldPatchFiles);
    if (count($newPatchFiles) > 0) {
      printLine("Applying new database patches");
      $user = getUser();
      if ($user) {
        $sql = $user->getSQL();
        foreach ($newPatchFiles as $patchFile) {
          if (preg_match("/core\/Configuration\/(Patch\/.*)\.class\.php/", $patchFile, $match)) {
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
  $user = getUser() or die();
  $action = $argv[2] ?? "list";

  if ($action === "list" || $action === "get") {
    $key = (($action === "list" || count($argv) < 4) ? null : $argv[3]);
    $req = new Api\Settings\Get($user);
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
      $req = new Api\Settings\Set($user);
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
      $req = new Api\Settings\Set($user);
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

  $user = getUser() or die();
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

    $req  = new Api\Routes\Add($user);
    $success = $req->execute($params);
    if (!$success) {
      _exit($req->getLastError());
    } else {
      _exit("Route added successfully");
    }
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

      $req = new Api\Routes\Remove($user);
    } else if ($action === "enable") {
      $req = new Api\Routes\Enable($user);
    } else if ($action === "disable") {
      $req = new Api\Routes\Disable($user);
    } else if ($action === "modify") {
      $req = new Api\Routes\Update($user);
      $params["request"] = $argv[4];
      $params["action"] = $argv[5];
      $params["target"] = $argv[6];
      $params["extra"] = $argv[7] ?? "";
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
  $verbose = in_array("-v", $requestedTests);

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
  $action = $argv[2] ?? null;
  if ($action === "sync") {
    $user = getUser() or die();
    if (!$user->getConfiguration()->getSettings()->isMailEnabled()) {
      _exit("Mails are not configured yet.");
    }

    $req = new Api\Mail\Sync($user);
    printLine("Syncing emails…");
    if (!$req->execute()) {
      _exit("Error syncing mails: " . $req->getLastError());
    }

    _exit("Done.");
  } else if ($action === "send_queue") {
    $user = getUser() or die();
    $req = new \Api\Mail\SendQueue($user);
    $debug = in_array("debug", $argv);
    if (!$req->execute(["debug" => $debug])) {
      _exit("Error processing mail queue: " . $req->getLastError());
    }
  } else {
    _exit("Usage: cli.php mail <sync|send_news|send_queue> [options...]");
  }
}

function onImpersonate($argv) {
  if (count($argv) < 3) {
    _exit("Usage: cli.php impersonate <user_id|user_name>");
  }

  $user = getUser() or exit;

  $userId = $argv[2];
  if (!is_numeric($userId)) {
    $sql = $user->getSQL();
    $res = $sql->select("uid")
      ->from("User")
      ->where(new Compare("name", $userId))
      ->execute();
    if ($res === false) {
      _exit("SQL error: " . $sql->getLastError());
    } else {
      $userId = $res[0]["uid"];
    }
  }

  $user->createSession(intval($userId));
  $session = $user->getSession();
  $session->setData(["2faAuthenticated" => true]);
  $session->update(false);
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