<?php

namespace Core\Objects\DatabaseEntity\Controller;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Column\BigIntColumn;
use Core\Driver\SQL\Column\BoolColumn;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Column\DateTimeColumn;
use Core\Driver\SQL\Column\EnumColumn;
use Core\Driver\SQL\Column\IntColumn;
use Core\Driver\SQL\Column\JsonColumn;
use Core\Driver\SQL\Column\StringColumn;
use Core\Driver\SQL\Condition\CondIn;
use Core\Driver\SQL\Condition\Condition;
use Core\Driver\SQL\Column\DoubleColumn;
use Core\Driver\SQL\Column\FloatColumn;
use Core\Driver\SQL\Condition\CondNot;
use Core\Driver\SQL\Constraint\ForeignKey;
use Core\Driver\SQL\Join\InnerJoin;
use Core\Driver\SQL\Query\CreateProcedure;
use Core\Driver\SQL\Query\CreateTable;
use Core\Driver\SQL\Query\Insert;
use Core\Driver\SQL\Query\Select;
use Core\Driver\SQL\SQL;
use Core\Driver\SQL\Strategy\CascadeStrategy;
use Core\Driver\SQL\Strategy\SetNullStrategy;
use Core\Driver\SQL\Strategy\UpdateStrategy;
use Core\Driver\SQL\Type\CurrentColumn;
use Core\Driver\SQL\Type\CurrentTable;
use Core\Objects\DatabaseEntity\Attribute\BigInt;
use Core\Objects\DatabaseEntity\Attribute\Enum;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\ExtendingEnum;
use Core\Objects\DatabaseEntity\Attribute\Json;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Attribute\Multiple;
use Core\Objects\DatabaseEntity\Attribute\MultipleReference;
use Core\Objects\DatabaseEntity\Attribute\Transient;
use Core\Objects\DatabaseEntity\Attribute\Unique;

class DatabaseEntityHandler implements Persistable {

  private \ReflectionClass $entityClass;
  private string $tableName;
  private array $columns;
  private array $properties;
  private array $relations;
  private array $constraints;
  private array $nmRelations;
  private array $extendingClasses;
  private ?\ReflectionProperty $extendingProperty;
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
    $this->relations = [];   // property name => DatabaseEntityHandler
    $this->constraints = []; // \Driver\SQL\Constraint\Constraint
    $this->nmRelations = []; // table name => NMRelation
    $this->extendingClasses = [];  // enum value => \ReflectionClass
    $this->extendingProperty = null;  // only one attribute can hold the type of the extending class
  }

  public function init() {
    $className = $this->entityClass->getName();


    $uniqueColumns = self::getAttribute($this->entityClass, Unique::class);
    if ($uniqueColumns) {
      $this->constraints[] = new \Core\Driver\SQL\Constraint\Unique($uniqueColumns->getColumns());
    }

    foreach ($this->entityClass->getProperties() as $property) {
      $propertyName = $property->getName();
      if ($propertyName === "id") {
        $this->properties[$propertyName] = $property;
        continue;
      }

      if ($property->isStatic()) {
        continue;
      }

      $propertyType = $property->getType();
      $columnName = self::buildColumnName($propertyName);
      if (!($propertyType instanceof \ReflectionNamedType)) {
        $this->raiseError("Cannot persist class '$className': Property '$propertyName' has no valid type");
      }

      $nullable = $propertyType->allowsNull();
      $propertyTypeName = $propertyType->getName();
      if (!empty($property->getAttributes(Transient::class))) {
        continue;
      }

      $ext = self::getAttribute($property, ExtendingEnum::class);
      if ($ext !== null) {
        if ($this->extendingProperty !== null) {
          $this->raiseError("Cannot have more than one extending property");
        } else {
          $this->extendingProperty = $property;
          $enumMappings = $ext->getMappings();
          foreach ($enumMappings as $key => $extendingClass) {
            if (!is_string($key)) {
              $type = gettype($key);
              $this->raiseError("Extending enum must be an array of string => class, got type '$type' for key: " . print_r($key, true));
            } else if (!is_string($extendingClass)) {
              $type = gettype($extendingClass);
              $this->raiseError("Extending enum must be an array of string => class, got type '$type' for value: " . print_r($extendingClass, true));
            }

            try {
              $requestedClass = new \ReflectionClass($extendingClass);
              if (!$requestedClass->isSubclassOf($this->entityClass)) {
                $this->raiseError("Class '$extendingClass' must be an inheriting from '" . $this->entityClass->getName() . "' for an extending enum");
              } else {
                $this->extendingClasses[$key] = $requestedClass;
              }
            } catch (\ReflectionException $ex) {
              $this->raiseError("Cannot persist extending enum for class $extendingClass: " . $ex->getMessage());
            }
          }
        }
      }

      $defaultValue = (self::getAttribute($property, DefaultValue::class))?->getValue();
      $isUnique = !empty($property->getAttributes(Unique::class));

      if ($propertyTypeName === 'string') {
        $enum = self::getAttribute($property, Enum::class);
        if ($enum) {
          $this->columns[$propertyName] = new EnumColumn($columnName, $enum->getValues(), $nullable, $defaultValue);
        } else {
          $bigInt = self::getAttribute($property, BigInt::class);
          if ($bigInt) {
            $this->columns[$propertyName] = new BigIntColumn($columnName, $nullable, $defaultValue, $bigInt->isUnsigned());
          } else {
            $maxLength = self::getAttribute($property, MaxLength::class);
            $this->columns[$propertyName] = new StringColumn($columnName, $maxLength?->getValue(), $nullable, $defaultValue);
          }
        }
      } else if ($propertyTypeName === 'int') {
        $bigInt = self::getAttribute($property, BigInt::class);
        if ($bigInt) {
          $this->columns[$propertyName] = new BigIntColumn($columnName, $nullable, $defaultValue, $bigInt->isUnsigned());
        } else {
          $this->columns[$propertyName] = new IntColumn($columnName, $nullable, $defaultValue);
        }
      } else if ($propertyTypeName === 'float') {
        $this->columns[$propertyName] = new FloatColumn($columnName, $nullable, $defaultValue);
      } else if ($propertyTypeName === 'double') {
        $this->columns[$propertyName] = new DoubleColumn($columnName, $nullable, $defaultValue);
      } else if ($propertyTypeName === 'bool') {
        $this->columns[$propertyName] = new BoolColumn($columnName, $defaultValue ?? false);
      } else if ($propertyTypeName === 'DateTime') {
        $this->columns[$propertyName] = new DateTimeColumn($columnName, $nullable, $defaultValue);
      } else if ($propertyTypeName === "array") {
        $json = self::getAttribute($property, Json::class);
        if ($json) {
          $this->columns[$propertyName] = new JsonColumn($columnName, $nullable, $defaultValue);
        } else {

          $multiple = self::getAttribute($property, Multiple::class);
          $multipleReference = self::getAttribute($property, MultipleReference::class);
          if (!$multiple && !$multipleReference) {
            $this->raiseError("Cannot persist class '$className': Property '$propertyName' has non persist-able type: $propertyTypeName. " .
              "Is the 'Multiple' or 'MultipleReference' attribute missing?");
          }

          try {
            $refClass = $multiple ? $multiple->getClassName() : $multipleReference->getClassName();
            $requestedClass = new \ReflectionClass($refClass);
            if ($requestedClass->isSubclassOf(DatabaseEntity::class)) {
              $otherHandler = DatabaseEntity::getHandler($this->sql, $requestedClass);
              if ($multiple) {
                $nmRelation = new NMRelation($this, $property, $otherHandler);
                $this->nmRelations[$propertyName] = $nmRelation;
              } else {
                $thisProperty = $multipleReference->getThisProperty();
                $relProperty = $multipleReference->getRelProperty();
                $nmRelationReference = new NMRelationReference($otherHandler, $thisProperty, $relProperty);
                $this->nmRelations[$propertyName] = $nmRelationReference;
              }
            } else {
              $this->raiseError("Cannot persist class '$className': Property '$propertyName' of type multiple can " .
                "only reference DatabaseEntity types, but got: $refClass");
            }
          } catch (\Exception $ex) {
            $this->raiseError("Cannot persist class '$className' property '$propertyTypeName': " . $ex->getMessage());
          }
        }
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
        $this->constraints[] = new \Core\Driver\SQL\Constraint\Unique($columnName);
      }
    }
  }

  public function getNMRelations(): array {
    return $this->nmRelations;
  }

  public static function getAttribute(\ReflectionProperty|\ReflectionClass $property, string $attributeClass): ?object {
    $attributes = $property->getAttributes($attributeClass);
    $attribute = array_shift($attributes);
    return $attribute?->newInstance();
  }

  public static function buildColumnName(string $propertyName): string {
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

  public function getColumnName(string $property, bool $withTableName = true): string {
    if ($withTableName) {
      if ($property === "id") {
        return "$this->tableName.id";
      } else {
        return $this->tableName . "." . $this->columns[$property]->getName();
      }
    } else {
      if ($property === "id") {
        return "id";
      } else {
        return $this->columns[$property]->getName();
      }
    }
  }

  public function getColumnNames(): array {
    $columns = ["$this->tableName.id"];
    foreach (array_keys($this->columns) as $property) {
      $columns[] = $this->getColumnName($property);
    }

    return $columns;
  }

  public function getColumns(): array {
    return $this->columns;
  }

  public function dependsOn(): array {
    $foreignTables = array_filter(array_map(
      function (DatabaseEntityHandler $relationHandler) {
        return $relationHandler->getTableName();
      }, $this->relations),
      function ($tableName) {
        return $tableName !== $this->getTableName();
      });
    return array_unique($foreignTables);
  }

  public function getNMRelation(string $property): Persistable {
    return $this->nmRelations[$property];
  }

  public function getProperty(string $property): \ReflectionProperty {
    return $this->properties[$property];
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

  private function getValueFromRow(array $row, string $propertyName, mixed &$value, bool $initEntities = false): bool {
    $column = $this->columns[$propertyName] ?? null;
    if (!$column) {
      return false;
    }

    $columnName = $column->getName();
    if (!array_key_exists($columnName, $row)) {
      return false;
    }

    $value = $row[$columnName];
    if ($column instanceof DateTimeColumn) {
      $value = new \DateTime($value);
    } else if ($column instanceof JsonColumn) {
      $value = json_decode($value);
    } else if (isset($this->relations[$propertyName])) {
      $relColumnPrefix = self::buildColumnName($propertyName) . "_";
      if (array_key_exists($relColumnPrefix . "id", $row)) {
        $relId = $row[$relColumnPrefix . "id"];
        if ($relId !== null) {
          if ($initEntities) {
            $relationHandler = $this->relations[$propertyName];
            $value = $relationHandler->entityFromRow(self::getPrefixedRow($row, $relColumnPrefix), [], true);
          } else {
            return false;
          }
        } else if (!$column->notNull()) {
          $value = null;
        } else {
          return false;
        }
      } else {
        return false;
      }
    }

    return true;
  }

  public function entityFromRow(array $row, array $additionalColumns = [], bool $initEntities = false): ?DatabaseEntity {
    try {

      $constructorClass = $this->entityClass;
      if ($this->extendingProperty !== null) {
        if ($this->getValueFromRow($row, $this->extendingProperty->getName(), $enumValue)) {
          if ($enumValue && isset($this->extendingClasses[$enumValue])) {
            $constructorClass = $this->extendingClasses[$enumValue];
          }
        }
      }

      $entity = call_user_func($constructorClass->getName() . "::newInstance", $constructorClass);
      if (!($entity instanceof DatabaseEntity)) {
        $this->logger->error("Created Object is not of type DatabaseEntity");
        return null;
      }

      foreach ($this->properties as $property) {
        if ($this->getValueFromRow($row, $property->getName(), $value, $initEntities)) {
          $property->setAccessible(true);
          $property->setValue($entity, $value);
        }
      }

      foreach ($additionalColumns as $column) {
        if (!in_array($column, $this->columns) && !isset($this->properties[$column])) {
          $entity[$column] = $row[$column];
        }
      }

      // init n:m / 1:n properties with empty arrays
      foreach ($this->nmRelations as $propertyName => $nmRelation) {
        $property = $this->properties[$propertyName];
        $property->setAccessible(true);
        $property->setValue($entity, []);
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

  public function updateNM(DatabaseEntity $entity, ?array $properties = null): bool {
    if (empty($this->nmRelations)) {
      return true;
    }

    foreach ($this->nmRelations as $nmProperty => $nmRelation) {
      $property = $this->properties[$nmProperty];
      $nmTable = $nmRelation->getTableName();

      if ($nmRelation instanceof NMRelation) {
        $thisIdColumn = $nmRelation->getIdColumn($this);
        $otherHandler = $nmRelation->getOtherHandler($this);
        $refIdColumn = $nmRelation->getIdColumn($otherHandler);
      } else if ($nmRelation instanceof NMRelationReference) {
        $otherHandler = $nmRelation->getRelHandler();
        $thisIdColumn = $otherHandler->getColumnName($nmRelation->getThisProperty(), false);
        $refIdColumn = $otherHandler->getColumnName($nmRelation->getRefProperty(), false);
      } else {
        throw new \Exception("updateNM not implemented for type: " . get_class($nmRelation));
      }

      // delete from n:m table if no longer exists
      $deleteStatement = $this->sql->delete($nmTable)
        ->whereEq($thisIdColumn, $entity->getId());  // this condition is important

      if ($properties === null || in_array($nmProperty, $properties)) {
        $entityIds = array_keys($property->getValue($entity));
        if (!empty($entityIds)) {
          $deleteStatement->where(
            new CondNot(new CondIn(new Column($refIdColumn), $entityIds))
          );
        }
        $deleteStatement->execute();
      }
    }

    return $this->insertNM($entity, true, $properties);
  }

  public function insertNM(DatabaseEntity $entity, bool $ignoreExisting = true, ?array $properties = null): bool {

    if (empty($this->nmRelations)) {
      return true;
    }

    $success = true;
    foreach ($this->nmRelations as $nmProperty => $nmRelation) {

      if ($properties !== null && !in_array($nmProperty, $properties)) {
        continue;
      }

      if ($nmRelation instanceof NMRelation) {
        $otherHandler = $nmRelation->getOtherHandler($this);
        $thisIdColumn = $nmRelation->getIdColumn($this);
        $refIdColumn = $nmRelation->getIdColumn($otherHandler);
      } else if ($nmRelation instanceof NMRelationReference) {
        $otherHandler = $nmRelation->getRelHandler();
        $thisIdColumn = $otherHandler->getColumnName($nmRelation->getThisProperty(), false);
        $refIdColumn = $otherHandler->getColumnName($nmRelation->getRefProperty(), false);
      } else {
        throw new \Exception("insertNM not implemented for type: " . get_class($nmRelation));
      }

      $property = $this->properties[$nmProperty];
      $property->setAccessible(true);
      $relEntities = $property->getValue($entity);
      if (!empty($relEntities)) {
        if ($nmRelation instanceof NMRelation) {
          $columns = [$thisIdColumn, $refIdColumn];
          $nmTable = $nmRelation->getTableName();
          $statement = $this->sql->insert($nmTable, $columns);
          if ($ignoreExisting) {
            $statement->onDuplicateKeyStrategy(new UpdateStrategy($columns, [
              $thisIdColumn => $entity->getId()
            ]));
          }
          foreach ($relEntities as $relEntity) {
            $relEntityId = (is_int($relEntity) ? $relEntity : $relEntity->getId());
            $statement->addRow($entity->getId(), $relEntityId);
          }
          $success = $statement->execute() && $success;
        } else if ($nmRelation instanceof NMRelationReference) {
          $otherHandler = $nmRelation->getRelHandler();
          $thisIdProperty = $otherHandler->properties[$nmRelation->getThisProperty()];
          $thisIdProperty->setAccessible(true);

          foreach ($relEntities as $relEntity) {
            $thisIdProperty->setValue($relEntity, $entity);
          }

          $success = $otherHandler->getInsertQuery($relEntities)->execute() && $success;
        }
      }
    }

    return $success;
  }

  public function fetchNMRelations(array $entities, bool $recursive = false) {

    if ($recursive) {
      foreach ($entities as $entity) {
        foreach ($this->relations as $propertyName => $relHandler) {
          $property = $this->properties[$propertyName];
          if ($property->isInitialized($entity)) {
            $relEntity = $this->properties[$propertyName]->getValue($entity);
            if ($relEntity) {
              $relHandler->fetchNMRelations([$relEntity->getId() => $relEntity], true);
            }
          }
        }
      }
    }

    if (empty($this->nmRelations)) {
      return;
    }

    $entityIds = array_keys($entities);
    foreach ($this->nmRelations as $nmProperty => $nmRelation) {
      $nmTable = $nmRelation->getTableName();
      $property = $this->properties[$nmProperty];
      $property->setAccessible(true);

      if ($nmRelation instanceof NMRelation) {
        $thisIdColumn = $nmRelation->getIdColumn($this);
        $otherHandler = $nmRelation->getOtherHandler($this);
        $refIdColumn = $nmRelation->getIdColumn($otherHandler);
        $refTableName = $otherHandler->getTableName();

        $relEntityQuery = DatabaseEntityQuery::fetchAll($otherHandler)
          ->addJoin(new InnerJoin($nmTable, "$nmTable.$refIdColumn", "$refTableName.id"))
          ->addSelectValue(new Column($thisIdColumn))
          ->where(new CondIn(new Column($thisIdColumn), $entityIds));

        if ($recursive) {
          $relEntityQuery->fetchEntities(true);
        }

        $rows = $relEntityQuery->executeSQL();
        if (!is_array($rows)) {
          $this->logger->error("Error fetching n:m relations from table: '$nmTable': " . $this->sql->getLastError());
          return;
        }

        $relEntities = [];
        foreach ($rows as $row) {
          $relId = $row["id"];
          if (!isset($relEntities[$relId])) {
            $relEntity = $otherHandler->entityFromRow($row, [], $recursive);
            $relEntities[$relId] = $relEntity;
          }

          $thisEntity = $entities[$row[$thisIdColumn]];
          $relEntity = $relEntities[$relId];

          $targetArray = $property->getValue($thisEntity);
          $targetArray[$relEntity->getId()] = $relEntity;
          $property->setValue($thisEntity, $targetArray);
        }
      } else if ($nmRelation instanceof NMRelationReference) {
        $otherHandler = $nmRelation->getRelHandler();
        $thisIdColumn = $otherHandler->getColumnName($nmRelation->getThisProperty(), false);
        $relIdColumn  = $otherHandler->getColumnName($nmRelation->getRefProperty(), false);
        if (!empty($entityIds)) {
          $relEntityQuery = DatabaseEntityQuery::fetchAll($otherHandler)
            ->where(new CondIn(new Column($thisIdColumn), $entityIds));

          if ($recursive) {
            $relEntityQuery->fetchEntities(true);
          }

          $rows = $relEntityQuery->executeSQL();
          if (!is_array($rows)) {
            $this->logger->error("Error fetching n:m relations from table: '$nmTable': " . $this->sql->getLastError());
            return;
          }

          $thisIdProperty = $otherHandler->properties[$nmRelation->getThisProperty()];
          $thisIdProperty->setAccessible(true);

          foreach ($rows as $row) {
            $relEntity = $otherHandler->entityFromRow($row, [], $recursive);
            $thisEntity = $entities[$row[$thisIdColumn]];
            $thisIdProperty->setValue($relEntity, $thisEntity);
            $targetArray = $property->getValue($thisEntity);
            $targetArray[$row[$relIdColumn]] = $relEntity;
            $property->setValue($thisEntity, $targetArray);
          }
        }
      } else {
        $this->logger->error("fetchNMRelations for type '" . get_class($nmRelation) . "' is not implemented");
        continue;
      }

      if ($recursive) {
        foreach ($entities as $entity) {
          $relEntities = $property->getValue($entity);
          $otherHandler->fetchNMRelations($relEntities);
        }
      }
    }
  }

  public function getSelectQuery(): Select {
    return $this->sql->select(...$this->getColumnNames())
      ->from($this->tableName);
  }

  public function fetchOne(int $id): DatabaseEntity|bool|null {
    $res = $this->getSelectQuery()
      ->whereEq($this->tableName . ".id", $id)
      ->first()
      ->execute();

    if ($res !== false && $res !== null) {
      $res = $this->entityFromRow($res);
      if ($res instanceof DatabaseEntity) {
        $this->fetchNMRelations([$res->getId() => $res]);
      }
    }

    return $res;
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

      $this->fetchNMRelations($entities);
      return $entities;
    }
  }

  public function getCreateQueries(SQL $sql): array {

    $queries = [];
    $table = $this->getTableName();

    // Create Table
    $queries[] = $this->getTableQuery($sql);

    // pre defined values
    $getPredefinedValues = $this->entityClass->getMethod("getPredefinedValues");
    $getPredefinedValues->setAccessible(true);
    $predefinedValues = $getPredefinedValues->invoke(null);
    if ($predefinedValues) {
      $queries[] = $this->getInsertQuery($predefinedValues);
    }

    // Entity Log
    $entityLogConfig = $this->entityClass->getProperty("entityLogConfig");
    $entityLogConfig->setAccessible(true);
    $entityLogConfig = $entityLogConfig->getValue();

    if (isset($entityLogConfig["insert"]) && $entityLogConfig["insert"] === true) {
      $queries[] = $sql->createTrigger("${table}_trg_insert")
        ->after()->insert($table)
        ->exec(new CreateProcedure($sql, "InsertEntityLog"), [
          "tableName" => new CurrentTable(),
          "entityId" => new CurrentColumn("id"),
          "lifetime" => $entityLogConfig["lifetime"] ?? 90,
        ]);
    }

    if (isset($entityLogConfig["update"]) && $entityLogConfig["update"] === true) {
      $queries[] = $sql->createTrigger("${table}_trg_update")
        ->after()->update($table)
        ->exec(new CreateProcedure($sql, "UpdateEntityLog"), [
          "tableName" => new CurrentTable(),
          "entityId" => new CurrentColumn("id"),
        ]);
    }

    if (isset($entityLogConfig["delete"]) && $entityLogConfig["delete"] === true) {
      $queries[] = $sql->createTrigger("${table}_trg_delete")
        ->after()->delete($table)
        ->exec(new CreateProcedure($sql, "DeleteEntityLog"), [
          "tableName" => new CurrentTable(),
          "entityId" => new CurrentColumn("id"),
        ]);
    }


    return $queries;
  }

  public function getTableQuery(SQL $sql): CreateTable {
    $query = $sql->createTable($this->tableName)
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

  private function prepareRow(DatabaseEntity $entity, string $action, ?array $properties = null): bool|array {
    $row = [];
    foreach ($this->columns as $propertyName => $column) {
      if ($properties !== null && !in_array($propertyName, $properties)) {
        continue;
      }

      $property = $this->properties[$propertyName];
      $property->setAccessible(true);
      if ($property->isInitialized($entity)) {
        $value = $property->getValue($entity);
        if (isset($this->relations[$propertyName])) {
          $value = $value?->getId();
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

  public function update(DatabaseEntity $entity, ?array $properties = null, bool $saveNM = false) {
    $row = $this->prepareRow($entity, "update", $properties);
    if ($row === false) {
      return false;
    }

    $entity->preInsert($row);
    $query = $this->sql->update($this->tableName)
      ->whereEq($this->tableName . ".id", $entity->getId());

    foreach ($row as $columnName => $value) {
      $query->set($columnName, $value);
    }

    $res = empty($row) ? true : $query->execute();
    if ($res && $saveNM) {
      $res = $this->updateNM($entity, $properties);
    }

    $entity->postUpdate();
    return $res;
  }

  public function insert(DatabaseEntity $entity): bool|int {
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

  public function insertOrUpdate(DatabaseEntity $entity, ?array $properties = null, bool $saveNM = false) {
    $id = $entity->getId();
    if ($id === null) {
      return $this->insert($entity);
    } else {
      return $this->update($entity, $properties, $saveNM);
    }
  }

  public function delete(int $id) {
    return $this->sql
      ->delete($this->tableName)
      ->whereEq($this->tableName . ".id", $id)
      ->execute();
  }

  private function raiseError(string $message) {
    $this->logger->error($message);
    throw new \Exception($message);
  }

  public function getSQL(): SQL {
    return $this->sql;
  }

  public function getInsertQuery(DatabaseEntity|array $entities): ?Insert {

    if (empty($entities)) {
      return null;
    }

    $firstEntity = (is_array($entities) ? current($entities) : $entities);
    $firstRow = $this->prepareRow($firstEntity, "insert");
    if ($firstRow === false) {
      return null;
    }

    $statement = $this->sql->insert($this->tableName, array_keys($firstRow))
      ->addRow(...array_values($firstRow));

    if (is_array($entities)) {
      foreach ($entities as $entity) {
        if ($entity === $firstEntity) {
          continue;
        }

        $row = $this->prepareRow($entity, "insert");
        $statement->addRow(...array_values($row));
      }
    }

    return $statement;
  }
}