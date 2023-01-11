<?php

namespace Core\Objects\DatabaseEntity\Controller;

use Core\Driver\SQL\Query\CreateTable;
use Core\Driver\SQL\SQL;
use Core\Driver\SQL\Strategy\CascadeStrategy;

class NMRelation implements Persistable {

  private DatabaseEntityHandler $thisHandler;
  private DatabaseEntityHandler $otherHandler;
  private \ReflectionProperty $property;
  private string $tableName;

  public function __construct(DatabaseEntityHandler $thisHandler, \ReflectionProperty $thisProperty, DatabaseEntityHandler $otherHandler) {
    $this->thisHandler = $thisHandler;
    $this->otherHandler = $otherHandler;
    $this->property = $thisProperty;
    $this->tableName = "NM_" . $thisHandler->getTableName() . "_" .
      DatabaseEntityHandler::buildColumnName($thisProperty->getName());
  }

  public function getIdColumn(DatabaseEntityHandler $handler): string {
    return DatabaseEntityHandler::buildColumnName($handler->getTableName()) . "_id";
  }

  public function getProperty(): \ReflectionProperty {
    return $this->property;
  }

  public function getTableQuery(SQL $sql): CreateTable {

    $thisTable = $this->thisHandler->getTableName();
    $otherTable = $this->otherHandler->getTableName();
    $thisIdColumn = $this->getIdColumn($this->thisHandler);
    $otherIdColumn = $this->getIdColumn($this->otherHandler);

    return $sql->createTable($this->tableName)
      ->addInt($thisIdColumn)
      ->addInt($otherIdColumn)
      ->foreignKey($thisIdColumn, $thisTable, "id", new CascadeStrategy())
      ->foreignKey($otherIdColumn, $otherTable, "id", new CascadeStrategy())
      ->unique($thisIdColumn, $otherIdColumn);
  }

  public function dependsOn(): array {
    return [$this->thisHandler->getTableName(), $this->otherHandler->getTableName()];
  }

  public function getTableName(): string {
    return $this->tableName;
  }

  public function getCreateQueries(SQL $sql): array {
    return [$this->getTableQuery($sql)];
  }

  public function getOtherHandler(DatabaseEntityHandler $handler): DatabaseEntityHandler {
    if ($handler === $this->thisHandler) {
      return $this->otherHandler;
    } else {
      return $this->thisHandler;
    }
  }
}