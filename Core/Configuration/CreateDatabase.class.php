<?php

namespace Core\Configuration;

use Core\API\Request;
use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Column\IntColumn;
use Core\Driver\SQL\Query\CreateTable;
use Core\Driver\SQL\SQL;
use Core\Driver\SQL\Type\CurrentColumn;
use Core\Driver\SQL\Type\CurrentTable;
use Core\Driver\SQL\Type\Trigger;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;
use PHPUnit\Util\Exception;

class CreateDatabase {

  private static ?Logger $logger = null;

  public static function getLogger(SQL $sql): Logger {
    if (self::$logger === null) {
      self::$logger = new Logger("CreateDatabase", $sql);
    }

    return self::$logger;
  }

  public static function createQueries(SQL $sql): array {
    $queries = array();

    self::loadEntities($sql, $queries);

    $queries[] = $sql->createTable("Settings")
      ->addString("name", 32)
      ->addJson("value", true)
      ->addBool("private", false) // these values are not returned from '/api/settings/get', but can be changed
      ->addBool("readonly", false) // these values are neither returned, nor can be changed from outside
      ->primaryKey("name");

    $defaultSettings = Settings::loadDefaults(loadEnv());
    $settingsQuery = $sql->insert("Settings", ["name", "value", "private", "readonly"]);
    $defaultSettings->addRows($settingsQuery);
    $queries[] = $settingsQuery;

    $queries[] = $sql->createTable("ApiPermission")
      ->addString("method", 32)
      ->addJson("groups", true, '[]')
      ->addString("description", 128, false, "")
      ->primaryKey("method")
      ->addBool("is_core", false);

    self::loadEntityLog($sql, $queries);
    self::loadDefaultACL($sql, $queries);
    return $queries;
  }

  private static function getCreatedTables(SQL $sql, array $queries): ?array {
    $createdTables = $sql->listTables();

    if ($createdTables !== null) {
      foreach ($queries as $query) {
        if ($query instanceof CreateTable) {
          $tableName = $query->getTableName();
          if (!in_array($tableName, $createdTables)) {
            $createdTables[] = $tableName;
          }
        }
      }
    } else {
      self::getLogger($sql)->warning("Error querying existing tables: " . $sql->getLastError());
    }

    return $createdTables;
  }

  public static function createEntityQueries(SQL $sql, array $entityClasses, array &$queries, bool $skipExisting = false): void {

    if (empty($entityClasses)) {
      return;
    }

    // first, check what tables are already created
    $createdTables = self::getCreatedTables($sql, $queries);
    if ($createdTables === null) {
      throw new \Exception("Error querying existing tables");
    }

    // then collect all persistable entities (tables, relations, etc.)
    $persistables = [];
    foreach ($entityClasses as $className) {
      $reflectionClass = new \ReflectionClass($className);
      if ($reflectionClass->isSubclassOf(DatabaseEntity::class)) {
        $handler = ("$className::getHandler")($sql, null, true);
        $persistables[$handler->getTableName()] = $handler;
        foreach ($handler->getNMRelations() as $nmTableName => $nmRelation) {
          $persistables[$nmTableName] = $nmRelation;
        }
      } else {
        throw new \Exception("Class '$className' is not a subclass of DatabaseEntity");
      }
    }

    // now order the persistable entities so all dependencies are met.
    $tableCount = count($persistables);
    while (!empty($persistables)) {
      $prevCount = $tableCount;
      $unmetDependenciesTotal = [];

      foreach ($persistables as $tableName => $persistable) {
        $dependsOn = $persistable->dependsOn();
        $unmetDependencies = array_diff($dependsOn, $createdTables);
        if (empty($unmetDependencies)) {
          $queries = array_merge($queries, $persistable->getCreateQueries($sql, $skipExisting));
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

  private static function loadEntities(SQL $sql, array &$queries): void {

    $classes = [];
    $baseDirs = ["Core", "Site"];
    foreach ($baseDirs as $baseDir) {
      $entityDirectory = "./$baseDir/Objects/DatabaseEntity/";
      if (file_exists($entityDirectory) && is_dir($entityDirectory)) {
        $scan_arr = scandir($entityDirectory);
        $files_arr = array_diff($scan_arr, [".", ".."]);
        foreach ($files_arr as $file) {
          $suffix = ".class.php";
          if (endsWith($file, $suffix)) {
            $className = substr($file, 0, strlen($file) - strlen($suffix));
            $className = "\\$baseDir\\Objects\\DatabaseEntity\\$className";
            $reflectionClass = new \ReflectionClass($className);
            if ($reflectionClass->isSubclassOf(DatabaseEntity::class)) {
              $classes[] = $className;
            }
          }
        }
      }
    }

    self::createEntityQueries($sql, $classes, $queries);
  }

  public static function loadDefaultACL(SQL $sql, array &$queries, ?array $classes = NULL): void {
    $query = $sql->insert("ApiPermission", ["method", "groups", "description", "is_core"]);

    if ($classes === NULL) {
      $classes = Request::getApiEndpoints();
    }

    foreach ($classes as $class) {
      if ($class instanceof \ReflectionClass) {
        $className = $class->getName();
      } else if (!is_string($class)) {
        throw new \Exception("Cannot call loadDefaultACL() for type: " . get_class($class));
      } else {
        $className = $class;
      }

      ("$className::loadDefaultACL")($query);
    }

    if ($query->hasRows()) {
      $queries[] = $query;
    }
  }

  private static function loadEntityLog(SQL $sql, array &$queries) {
    $queries[] = $sql->createTable("EntityLog")
      ->addInt("entity_id")
      ->addString("table_name")
      ->addDateTime("last_modified", false, $sql->now())
      ->addInt("lifetime", false, 90);

    $insertProcedure = $sql->createProcedure("InsertEntityLog")
      ->param(new CurrentTable())
      ->param(new IntColumn("id"))
      ->param(new IntColumn("lifetime", false, 90))
      ->returns(new Trigger())
      ->exec(array(
        $sql->insert("EntityLog", ["entity_id", "table_name", "lifetime"])
          ->addRow(new CurrentColumn("id"), new CurrentTable(), new CurrentColumn("lifetime"))
      ));

    $updateProcedure = $sql->createProcedure("UpdateEntityLog")
      ->param(new CurrentTable())
      ->param(new IntColumn("id"))
      ->returns(new Trigger())
      ->exec(array(
        $sql->update("EntityLog")
          ->set("last_modified", $sql->now())
          ->whereEq("entity_id", new CurrentColumn("id"))
          ->whereEq("table_name", new CurrentTable())
      ));

    $deleteProcedure = $sql->createProcedure("DeleteEntityLog")
      ->param(new CurrentTable())
      ->param(new IntColumn("id"))
      ->returns(new Trigger())
      ->exec(array(
        $sql->delete("EntityLog")
          ->whereEq("entity_id", new CurrentColumn("id"))
          ->whereEq("table_name", new CurrentTable())
      ));

    $queries[] = $insertProcedure;
    $queries[] = $updateProcedure;
    $queries[] = $deleteProcedure;
  }
}
