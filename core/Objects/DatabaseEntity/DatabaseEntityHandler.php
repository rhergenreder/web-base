<?php

namespace Objects\DatabaseEntity;

use Driver\Logger\Logger;
use Driver\SQL\Column\BoolColumn;
use Driver\SQL\Column\DateTimeColumn;
use Driver\SQL\Column\EnumColumn;
use Driver\SQL\Column\IntColumn;
use Driver\SQL\Column\JsonColumn;
use Driver\SQL\Column\StringColumn;
use Driver\SQL\Condition\Compare;
use Driver\SQL\Condition\Condition;
use Driver\SQL\Column\DoubleColumn;
use Driver\SQL\Column\FloatColumn;
use Driver\SQL\Constraint\ForeignKey;
use Driver\SQL\Query\CreateTable;
use Driver\SQL\Query\Select;
use Driver\SQL\SQL;
use Driver\SQL\Strategy\CascadeStrategy;
use Driver\SQL\Strategy\SetNullStrategy;
use Objects\DatabaseEntity\Attribute\Enum;
use Objects\DatabaseEntity\Attribute\DefaultValue;
use Objects\DatabaseEntity\Attribute\Json;
use Objects\DatabaseEntity\Attribute\Many;
use Objects\DatabaseEntity\Attribute\MaxLength;
use Objects\DatabaseEntity\Attribute\Transient;
use Objects\DatabaseEntity\Attribute\Unique;
use PHPUnit\Util\Exception;

class DatabaseEntityHandler {

  private \ReflectionClass $entityClass;
  private string $tableName;
  private array $columns;
  private array $properties;
  private array $relations;
  private array $constraints;
  private SQL $sql;
  private Logger $logger;

  public function __construct(SQL $sql, \ReflectionClass $entityClass) {
    $this->sql = $sql;
    $className = $entityClass->getName();
    $this->logger = new Logger($entityClass->getShortName(), $sql);
    $this->entityClass = $entityClass;
    if (!$this->entityClass->isSubclassOf(DatabaseEntity::class)) {
      $this->raiseError("Cannot persist class '$className': Not an instance of DatabaseEntity or not instantiable.");
    }

    $this->tableName = $this->entityClass->getShortName();
    $this->columns = [];     // property name => database column name
    $this->properties = [];  // property name => \ReflectionProperty
    $this->relations = [];   // property name => referenced table name
    $this->constraints = []; // \Driver\SQL\Constraint\Constraint

    foreach ($this->entityClass->getProperties() as $property) {
      $propertyName = $property->getName();
      if ($propertyName === "id") {
        $this->properties[$propertyName] = $property;
        continue;
      }

      $propertyType = $property->getType();
      $columnName = self::getColumnName($propertyName);
      if (!($propertyType instanceof \ReflectionNamedType)) {
        $this->raiseError("Cannot persist class '$className': Property '$propertyName' has no valid type");
      }

      $nullable = $propertyType->allowsNull();
      $propertyTypeName = $propertyType->getName();
      if (!empty($property->getAttributes(Transient::class))) {
        continue;
      }

      $defaultValue = (self::getAttribute($property, DefaultValue::class))?->getValue();
      $isUnique = !empty($property->getAttributes(Unique::class));

      if ($propertyTypeName === 'string') {
        $enum = self::getAttribute($property, Enum::class);
        if ($enum) {
          $this->columns[$propertyName] = new EnumColumn($columnName, $enum->getValues(), $nullable, $defaultValue);
        } else {
          $maxLength = self::getAttribute($property, MaxLength::class);
          $this->columns[$propertyName] = new StringColumn($columnName, $maxLength?->getValue(), $nullable, $defaultValue);
        }
      } else if ($propertyTypeName === 'int') {
        $this->columns[$propertyName] = new IntColumn($columnName, $nullable, $defaultValue);
      } else if ($propertyTypeName === 'float') {
        $this->columns[$propertyName] = new FloatColumn($columnName, $nullable, $defaultValue);
      } else if ($propertyTypeName === 'double') {
        $this->columns[$propertyName] = new DoubleColumn($columnName, $nullable, $defaultValue);
      } else if ($propertyTypeName === 'bool') {
        $this->columns[$propertyName] = new BoolColumn($columnName, $defaultValue ?? false);
      } else if ($propertyTypeName === 'DateTime') {
        $this->columns[$propertyName] = new DateTimeColumn($columnName, $nullable, $defaultValue);
      /*} else if ($propertyName === 'array') {
        $many = self::getAttribute($property, Many::class);
        if ($many) {
          $requestedType = $many->getValue();
          if (isClass($requestedType)) {
            $requestedClass = new \ReflectionClass($requestedType);
          } else {
            $this->raiseError("Cannot persist class '$className': Property '$propertyName' has non persist-able type: $requestedType");
          }
        } else {
          $this->raiseError("Cannot persist class '$className': Property '$propertyName' has non persist-able type: $propertyTypeName");
        }*/
      } else if ($propertyTypeName !== "mixed") {
        try {
          $requestedClass = new \ReflectionClass($propertyTypeName);
          if ($requestedClass->isSubclassOf(DatabaseEntity::class)) {
            $columnName .= "_id";
            $requestedHandler = ($requestedClass->getName() === $this->entityClass->getName()) ?
              $this : DatabaseEntity::getHandler($this->sql, $requestedClass);
            $strategy = $nullable ? new SetNullStrategy() : new CascadeStrategy();
            $this->columns[$propertyName] = new IntColumn($columnName, $nullable, $defaultValue);
            $this->constraints[] = new ForeignKey($columnName, $requestedHandler->tableName, "id", $strategy);
            $this->relations[$propertyName] = $requestedHandler;
          } else {
            $this->raiseError("Cannot persist class '$className': Property '$propertyName' has non persist-able type: $propertyTypeName");
          }
        } catch (\Exception $ex) {
          $this->raiseError("Cannot persist class '$className' property '$propertyTypeName': " . $ex->getMessage());
        }
      } else {
        if (!empty($property->getAttributes(Json::class))) {
          $this->columns[$propertyName] = new JsonColumn($columnName, $nullable, $defaultValue);
        } else {
          $this->raiseError("Cannot persist class '$className': Property '$propertyName' has non persist-able type: $propertyTypeName");
        }
      }

      $this->properties[$propertyName] = $property;

      if ($isUnique) {
        $this->constraints[] = new \Driver\SQL\Constraint\Unique($columnName);
      }
    }
  }

  private static function getAttribute(\ReflectionProperty $property, string $attributeClass): ?object {
    $attributes = $property->getAttributes($attributeClass);
    $attribute = array_shift($attributes);
    return $attribute?->newInstance();
  }

  public static function getColumnName(string $propertyName): string {
    // abcTestLOL => abc_test_lol
    return strtolower(preg_replace_callback("/([a-z])([A-Z]+)/", function ($m) {
      return $m[1] . "_" . strtolower($m[2]);
    }, $propertyName));
  }

  public function getReflection(): \ReflectionClass {
    return $this->entityClass;
  }

  public function getLogger(): Logger {
    return $this->logger;
  }

  public function getTableName(): string {
    return $this->tableName;
  }

  public function getRelations(): array {
    return $this->relations;
  }

  public function getColumnNames(): array {
    $columns = ["$this->tableName.id"];
    foreach ($this->columns as $column) {
      $columns[] = $this->tableName . "." . $column->getName();
    }

    return $columns;
  }

  public function getColumns(): array {
    return $this->columns;
  }

  public function dependsOn(): array {
    $foreignTables = array_map(function (DatabaseEntityHandler $relationHandler) {
      return $relationHandler->getTableName();
    }, $this->relations);
    return array_unique($foreignTables);
  }

  public static function getPrefixedRow(array $row, string $prefix): array {
    $rel_row = [];
    foreach ($row as $relKey => $relValue) {
      if (startsWith($relKey, $prefix)) {
        $rel_row[substr($relKey, strlen($prefix))] = $relValue;
      }
    }
    return $rel_row;
  }

  public function entityFromRow(array $row): ?DatabaseEntity {
    try {

      $entity = call_user_func($this->entityClass->getName() . "::newInstance", $this->entityClass, $row);
      if (!($entity instanceof DatabaseEntity)) {
        $this->logger->error("Created Object is not of type DatabaseEntity");
        return null;
      }

      foreach ($this->columns as $propertyName => $column) {
        $columnName = $column->getName();
        if (array_key_exists($columnName, $row)) {
          $value = $row[$columnName];
          $property = $this->properties[$propertyName];

          if ($column instanceof DateTimeColumn) {
            $value = new \DateTime($value);
          } else if ($column instanceof JsonColumn) {
            $value = json_decode($value);
          } else if (isset($this->relations[$propertyName])) {
            $relColumnPrefix = self::getColumnName($propertyName) . "_";
            if (array_key_exists($relColumnPrefix . "id", $row)) {
              $relId = $row[$relColumnPrefix . "id"];
              if ($relId !== null) {
                $relationHandler = $this->relations[$propertyName];
                $value = $relationHandler->entityFromRow(self::getPrefixedRow($row, $relColumnPrefix));
              } else if (!$column->notNull()) {
                $value = null;
              } else {
                continue;
              }
            } else {
              continue;
            }
          }

          $property->setAccessible(true);
          $property->setValue($entity, $value);
        }
      }

      $this->properties["id"]->setAccessible(true);
      $this->properties["id"]->setValue($entity, $row["id"]);
      $entity->postFetch($this->sql, $row);
      return $entity;
    } catch (\Exception $exception) {
      $this->logger->error("Error creating entity from database row: " . $exception->getMessage());
      return null;
    }
  }

  public function getSelectQuery(): Select {
    return $this->sql->select(...$this->getColumnNames())
      ->from($this->tableName);
  }

  public function fetchOne(int $id): DatabaseEntity|bool|null {
    $res = $this->getSelectQuery()
      ->where(new Compare($this->tableName . ".id", $id))
      ->first()
      ->execute();

    if ($res === false || $res === null) {
      return $res;
    } else {
      return $this->entityFromRow($res);
    }
  }

  public function fetchMultiple(?Condition $condition = null): ?array {
    $query = $this->getSelectQuery();

    if ($condition) {
      $query->where($condition);
    }

    $res = $query->execute();
    if ($res === false) {
      return null;
    } else {
      $entities = [];
      foreach ($res as $row) {
        $entity = $this->entityFromRow($row);
        if ($entity) {
          $entities[$entity->getId()] = $entity;
        }
      }
      return $entities;
    }
  }

  public function getTableQuery(): CreateTable {
    $query = $this->sql->createTable($this->tableName)
      ->onlyIfNotExists()
      ->addSerial("id")
      ->primaryKey("id");

    foreach ($this->columns as $column) {
      $query->addColumn($column);
    }

    foreach ($this->constraints as $constraint) {
      $query->addConstraint($constraint);
    }

    return $query;
  }

  public function createTable(): bool {
    $query = $this->getTableQuery();
    return $query->execute();
  }

  private function prepareRow(DatabaseEntity $entity, string $action, ?array $columns = null) {
    $row = [];
    foreach ($this->columns as $propertyName => $column) {
      if ($columns && !in_array($column->getName(), $columns)) {
        continue;
      }

      $property = $this->properties[$propertyName];
      $property->setAccessible(true);
      if ($property->isInitialized($entity)) {
        $value = $property->getValue($entity);
        if (isset($this->relations[$propertyName])) {
          $value = $value->getId();
        }
      } else if (!$this->columns[$propertyName]->notNull()) {
        $value = null;
      } else {
        $defaultValue = self::getAttribute($property, DefaultValue::class);
        if ($defaultValue) {
          $value = $defaultValue->getValue();
        } else if ($action !== "update") {
          $this->logger->error("Cannot $action entity: property '$propertyName' was not initialized yet.");
          return false;
        } else {
          continue;
        }
      }

      $row[$column->getName()] = $value;
    }

    return $row;
  }

  public function update(DatabaseEntity $entity, ?array $columns = null) {
    $row = $this->prepareRow($entity, "update", $columns);
    if ($row === false) {
      return false;
    }

    $entity->preInsert($row);
    $query = $this->sql->update($this->tableName)
      ->where(new Compare($this->tableName . ".id", $entity->getId()));

    foreach ($row as $columnName => $value) {
      $query->set($columnName, $value);
    }

    return $query->execute();
  }

  public function insert(DatabaseEntity $entity) {
    $row = $this->prepareRow($entity, "insert");
    if ($row === false) {
      return false;
    }

    $entity->preInsert($row);

    // insert with id?
    $entityId = $entity->getId();
    if ($entityId !== null) {
      $row["id"] = $entityId;
    }

    $query = $this->sql->insert($this->tableName, array_keys($row))
      ->addRow(...array_values($row));

    // return id if its auto-generated
    if ($entityId === null) {
      $query->returning("id");
    }

    $res = $query->execute();
    if ($res !== false) {
      return $this->sql->getLastInsertId();
    } else {
      return false;
    }
  }

  public function insertOrUpdate(DatabaseEntity $entity, ?array $columns = null) {
    $id = $entity->getId();
    if ($id === null) {
      return $this->insert($entity);
    } else {
      return $this->update($entity, $columns);
    }
  }

  public function delete(int $id) {
    return $this->sql
      ->delete($this->tableName)
      ->where(new Compare($this->tableName . ".id", $id))
      ->execute();
  }

  private function raiseError(string $message) {
    $this->logger->error($message);
    throw new Exception($message);
  }
}