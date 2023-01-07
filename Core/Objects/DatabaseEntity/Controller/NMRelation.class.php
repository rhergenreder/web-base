<?php

namespace Core\Objects\DatabaseEntity\Controller;

# TODO: Allow more than 2 relations here?

use Core\Driver\SQL\Query\CreateTable;
use Core\Driver\SQL\SQL;
use Core\Driver\SQL\Strategy\CascadeStrategy;

class NMRelation implements Persistable {

  private DatabaseEntityHandler $handlerA;
  private DatabaseEntityHandler $handlerB;
  private array $properties;

  public function __construct(DatabaseEntityHandler $handlerA, DatabaseEntityHandler $handlerB) {
    $this->handlerA = $handlerA;
    $this->handlerB = $handlerB;
    $tableNameA = $handlerA->getTableName();
    $tableNameB = $handlerB->getTableName();
    if ($tableNameA === $tableNameB) {
      throw new \Exception("Cannot create N:M Relation with only one table");
    }

    $this->properties = [
      $tableNameA => [],
      $tableNameB => [],
    ];
  }

  public function addProperty(DatabaseEntityHandler $src, \ReflectionProperty $property): void {
    $this->properties[$src->getTableName()][$property->getName()] = $property;
  }

  public function getIdColumn(DatabaseEntityHandler $handler): string {
    return DatabaseEntityHandler::buildColumnName($handler->getTableName()) . "_id";
  }

  public function getDataColumns(): array {

    $referenceCount = 0;
    $columnsNeeded = false;

    // if in one of the relations we have multiple references, we need to differentiate
    foreach ($this->properties as $refProperties) {
      $referenceCount += count($refProperties);
      if ($referenceCount > 1) {
        $columnsNeeded = true;
        break;
      }
    }

    $columns = [];
    if ($columnsNeeded) {
      foreach ($this->properties as $tableName => $properties) {
        $columns[$tableName] = [];
        foreach ($properties as $property) {
          $columnName = DatabaseEntityHandler::buildColumnName($tableName) . "_" .
            DatabaseEntityHandler::buildColumnName($property->getName());
          $columns[$tableName][$property->getName()] = $columnName;
        }
      }
    }

    return $columns;
  }

  public function getAllColumns(): array {
    $relIdA = $this->getIdColumn($this->handlerA);
    $relIdB = $this->getIdColumn($this->handlerB);

    $columns = [$relIdA, $relIdB];

    foreach ($this->getDataColumns() as $dataColumns) {
      foreach ($dataColumns as $columnName) {
        $columns[] = $columnName;
      }
    }

    return $columns;
  }

  public function getTableQuery(SQL $sql): CreateTable {

    $tableNameA = $this->handlerA->getTableName();
    $tableNameB = $this->handlerB->getTableName();

    $columns = $this->getAllColumns();
    list ($relIdA, $relIdB) = $columns;
    $dataColumns = array_slice($columns, 2);
    $query = $sql->createTable(self::buildTableName($tableNameA, $tableNameB))
      ->addInt($relIdA)
      ->addInt($relIdB)
      ->foreignKey($relIdA, $tableNameA, "id", new CascadeStrategy())
      ->foreignKey($relIdB, $tableNameB, "id", new CascadeStrategy());

    foreach ($dataColumns as $dataColumn) {
      $query->addBool($dataColumn, false);
    }

    $query->unique(...$columns);
    return $query;
  }

  public static function buildTableName(string ...$tables): string {
    // in case of class passed here
    $tables = array_map(function ($t) { return isClass($t) ? getClassName($t) : $t; }, $tables);
    sort($tables);
    return "NM_" . implode("_", $tables);
  }

  public function dependsOn(): array {
    return [$this->handlerA->getTableName(), $this->handlerB->getTableName()];
  }

  public function getTableName(): string {
    return self::buildTableName(...$this->dependsOn());
  }

  public function getCreateQueries(SQL $sql): array {
    return [$this->getTableQuery($sql)];
  }

  public function getProperties(DatabaseEntityHandler $handler): array {
    return $this->properties[$handler->getTableName()];
  }

  public function getOtherHandler(DatabaseEntityHandler $handler): DatabaseEntityHandler {
    if ($handler === $this->handlerA) {
      return $this->handlerB;
    } else {
      return $this->handlerA;
    }
  }
}