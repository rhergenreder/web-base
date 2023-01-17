<?php

namespace Core\Configuration;

use Core\API\Request;
use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;
use Core\Objects\DatabaseEntity\Group;
use Core\Objects\DatabaseEntity\Route;
use Core\Objects\Router\DocumentRoute;
use Core\Objects\Router\StaticFileRoute;
use PHPUnit\Util\Exception;

class CreateDatabase extends DatabaseScript {

  public static function createQueries(SQL $sql): array {
    $queries = array();

    self::loadEntities($queries, $sql);

    $queries[] = $sql->createTable("Visitor")
      ->addInt("day")
      ->addInt("count", false, 1)
      ->addString("cookie", 26)
      ->unique("day", "cookie");

    $queries[] = $sql->createTable("Settings")
      ->addString("name", 32)
      ->addString("value", 1024, true)
      ->addBool("private", false) // these values are not returned from '/api/settings/get', but can be changed
      ->addBool("readonly", false) // these values are neither returned, nor can be changed from outside
      ->primaryKey("name");
    $settingsQuery = $sql->insert("Settings", array("name", "value", "private", "readonly"));
    (Settings::loadDefaults())->addRows($settingsQuery);
    $queries[] = $settingsQuery;

    $queries[] = $sql->createTable("ApiPermission")
      ->addString("method", 32)
      ->addJson("groups", true, '[]')
      ->addString("description", 128, false, "")
      ->primaryKey("method");

    self::loadDefaultACL($queries, $sql);
    self::loadPatches($queries, $sql);

    return $queries;
  }

  private static function loadPatches(&$queries, $sql) {
    $baseDirs = ["Core", "Site"];
    foreach ($baseDirs as $baseDir) {
      $patchDirectory = "./$baseDir/Configuration/Patch/";
      if (file_exists($patchDirectory) && is_dir($patchDirectory)) {
        $scan_arr = scandir($patchDirectory);
        $files_arr = array_diff($scan_arr, array('.', '..'));
        foreach ($files_arr as $file) {
          $suffix = ".class.php";
          if (endsWith($file, $suffix)) {
            $className = substr($file, 0, strlen($file) - strlen($suffix));
            $className = "\\$baseDir\\Configuration\\Patch\\$className";
            $method = "$className::createQueries";
            $patchQueries = call_user_func($method, $sql);
            foreach ($patchQueries as $query) $queries[] = $query;
          }
        }
      }
    }
  }

  private static function loadEntities(&$queries, $sql) {
    $persistables = [];
    $baseDirs = ["Core", "Site"];
    foreach ($baseDirs as $baseDir) {
      $entityDirectory = "./$baseDir/Objects/DatabaseEntity/";
      if (file_exists($entityDirectory) && is_dir($entityDirectory)) {
        $scan_arr = scandir($entityDirectory);
        $files_arr = array_diff($scan_arr, array('.', '..'));
        foreach ($files_arr as $file) {
          $suffix = ".class.php";
          if (endsWith($file, $suffix)) {
            $className = substr($file, 0, strlen($file) - strlen($suffix));
            $className = "\\$baseDir\\Objects\\DatabaseEntity\\$className";
            $reflectionClass = new \ReflectionClass($className);
            if ($reflectionClass->isSubclassOf(DatabaseEntity::class)) {
              $method = "$className::getHandler";
              $handler = call_user_func($method, $sql, null, true);
              $persistables[$handler->getTableName()] = $handler;

              foreach ($handler->getNMRelations() as $nmTableName => $nmRelation) {
                $persistables[$nmTableName] = $nmRelation;
              }
            }
          }
        }
      }
    }

    $tableCount = count($persistables);
    $createdTables = [];
    while (!empty($persistables)) {
      $prevCount = $tableCount;
      $unmetDependenciesTotal = [];

      foreach ($persistables as $tableName => $persistable) {
        $dependsOn = $persistable->dependsOn();
        $unmetDependencies = array_diff($dependsOn, $createdTables);
        if (empty($unmetDependencies)) {
          $queries = array_merge($queries, $persistable->getCreateQueries($sql));
          $createdTables[] = $tableName;
          unset($persistables[$tableName]);
        } else {
          $unmetDependenciesTotal = array_merge($unmetDependenciesTotal, $unmetDependencies);
        }
      }

      $tableCount = count($persistables);
      if ($tableCount === $prevCount) {
        throw new Exception("Circular or unmet table dependency detected. Unmet dependencies: "
          . implode(", ", $unmetDependenciesTotal));
      }
    }
  }

  public static function loadDefaultACL(array &$queries, SQL $sql) {
    $query = $sql->insert("ApiPermission", ["method", "groups", "description"]);

    foreach (Request::getApiEndpoints() as $reflectionClass) {
      $method = $reflectionClass->getName() . "::getDefaultACL";
      $method($query);
    }

    if ($query->hasRows()) {
      $queries[] = $query;
    }
  }
}
