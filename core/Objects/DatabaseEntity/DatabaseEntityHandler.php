<?php

namespace Objects\DatabaseEntity;

use Driver\SQL\Column\BoolColumn;
use Driver\SQL\Column\DateTimeColumn;
use Driver\SQL\Column\IntColumn;
use Driver\SQL\Column\StringColumn;
use Driver\SQL\Condition\Compare;
use Driver\SQL\Condition\Condition;
use Driver\SQL\Column\DoubleColumn;
use Driver\SQL\Column\FloatColumn;
use Driver\SQL\Constraint\ForeignKey;
use Driver\SQL\SQL;
use Driver\SQL\Strategy\CascadeStrategy;
use Driver\SQL\Strategy\SetNullStrategy;
use PHPUnit\Util\Exception;

class DatabaseEntityHandler {

  private \ReflectionClass $entityClass;
  private string $tableName;
  private array $columns;
  private array $properties;

  public function __construct(\ReflectionClass $entityClass) {
    $className = $entityClass->getName();
    $this->entityClass = $entityClass;
    if (!$this->entityClass->isSubclassOf(DatabaseEntity::class) ||
      !$this->entityClass->isInstantiable()) {
      throw new Exception("Cannot persist class '$className': Not an instance of DatabaseEntity or not instantiable.");
    }

    $this->tableName = $this->entityClass->getShortName();
    $this->columns = [];
    $this->properties = [];
    $this->relations = [];

    foreach ($this->entityClass->getProperties() as $property) {
      $propertyName = $property->getName();
      $propertyType = $property->getType();
      $columnName = self::getColumnName($propertyName);
      if (!($propertyType instanceof \ReflectionNamedType)) {
        throw new Exception("Cannot persist class '$className': Property '$propertyName' has no valid type");
      }

      $nullable = $propertyType->allowsNull();
      $propertyTypeName = $propertyType->getName();
      if ($propertyTypeName === 'string') {
        $this->columns[$propertyName] = new StringColumn($columnName, null, $nullable);
      } else if ($propertyTypeName === 'int') {
        $this->columns[$propertyName] = new IntColumn($columnName, $nullable);
      } else if ($propertyTypeName === 'float') {
        $this->columns[$propertyName] = new FloatColumn($columnName, $nullable);
      } else if ($propertyTypeName === 'double') {
        $this->columns[$propertyName] = new DoubleColumn($columnName, $nullable);
      } else if ($propertyTypeName === 'bool') {
        $this->columns[$propertyName] = new BoolColumn($columnName, $nullable);
      } else if ($propertyTypeName === 'DateTime') {
        $this->columns[$propertyName] = new DateTimeColumn($columnName, $nullable);
      } else {
        try {
          $requestedClass = new \ReflectionClass($propertyTypeName);
          if ($requestedClass->isSubclassOf(DatabaseEntity::class)) {
            $requestedHandler = ($requestedClass->getName() === $this->entityClass->getName()) ?
              $this : DatabaseEntity::getHandler($requestedClass);
            $strategy = $nullable ? new SetNullStrategy() : new CascadeStrategy();
            $this->columns[$propertyName] = new IntColumn($columnName, $nullable);
            $this->relations[$propertyName] = new ForeignKey($columnName, $requestedHandler->tableName, "id", $strategy);
          }
        } catch (\Exception $ex) {
          throw new Exception("Cannot persist class '$className': Property '$propertyName' has non persist-able type: $propertyTypeName");
        }
      }

      $this->properties[$propertyName] = $property;
    }
  }

  private static function getColumnName(string $propertyName): string {
    // abcTestLOL => abc_test_lol
    return strtolower(preg_replace_callback("/([a-z])([A-Z]+)/", function ($m) {
      return $m[1] . "_" . strtolower($m[2]);
    }, $propertyName));
  }

  public function getReflection(): \ReflectionClass {
    return $this->entityClass;
  }

  private function entityFromRow(array $row): DatabaseEntity {
    $entity = $this->entityClass->newInstanceWithoutConstructor();
    foreach ($this->columns as $propertyName => $column) {
      $this->properties[$propertyName]->setValue($entity, $row[$column]);
    }
    return $entity;
  }

  public function fetchOne(SQL $sql, int $id): ?DatabaseEntity {
    $res = $sql->select(...array_keys($this->columns))
      ->from($this->tableName)
      ->where(new Compare("id", $id))
      ->first()
      ->execute();

    if (empty($res)) {
      return null;
    } else {
      return $this->entityFromRow($res);
    }
  }

  public function fetchMultiple(SQL $sql, ?Condition $condition = null): ?array {
    $query = $sql->select(...array_keys($this->columns))
      ->from($this->tableName);

    if ($condition) {
      $query->where($condition);
    }

    $res = $query->execute();
    if ($res === false) {
      return null;
    } else {
      $entities = [];
      foreach ($res as $row) {
        $entities[] = $this->entityFromRow($row);
      }
      return $entities;
    }
  }

  public function createTable(SQL $sql): bool {
    $query = $sql->createTable($this->tableName)
      ->onlyIfNotExists()
      ->addSerial("id")
      ->primaryKey("id");

    foreach ($this->columns as $column) {
      $query->addColumn($column);
    }

    foreach ($this->relations as $constraint) {
      $query->addConstraint($constraint);
    }

    return $query->execute();
  }

  public function insertOrUpdate(SQL $sql, DatabaseEntity $entity) {
    $id = $entity->getId();
    if ($id === null) {
      $columns = [];
      $row = [];

      foreach ($this->columns as $propertyName => $column) {
        $columns[] = $column->getName();
        $row[] = $this->properties[$propertyName]->getValue($entity);
      }

      $res = $sql->insert($this->tableName, $columns)
        ->addRow(...$row)
        ->returning("id")
        ->execute();

      if ($res !== false) {
        return $sql->getLastInsertId();
      } else {
        return false;
      }
    } else {
      $query = $sql->update($this->tableName)
        ->where(new Compare("id", $id));

      foreach ($this->columns as $propertyName => $column) {
        $columnName = $column->getName();
        $value = $this->properties[$propertyName]->getValue($entity);
        $query->set($columnName, $value);
      }

      return $query->execute();
    }
  }

  public function delete(SQL $sql, int $id) {
    return $sql->delete($this->tableName)->where(new Compare("id", $id))->execute();
  }
}