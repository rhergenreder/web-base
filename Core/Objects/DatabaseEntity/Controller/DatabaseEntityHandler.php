<?php

namespace Core\Objects\DatabaseEntity\Controller;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Column\BoolColumn;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Column\DateTimeColumn;
use Core\Driver\SQL\Column\EnumColumn;
use Core\Driver\SQL\Column\IntColumn;
use Core\Driver\SQL\Column\JsonColumn;
use Core\Driver\SQL\Column\StringColumn;
use Core\Driver\SQL\Condition\CondAnd;
use Core\Driver\SQL\Condition\CondBool;
use Core\Driver\SQL\Condition\CondIn;
use Core\Driver\SQL\Condition\Condition;
use Core\Driver\SQL\Column\DoubleColumn;
use Core\Driver\SQL\Column\FloatColumn;
use Core\Driver\SQL\Condition\CondNot;
use Core\Driver\SQL\Condition\CondOr;
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
use Core\Objects\DatabaseEntity\Attribute\Enum;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\ExtendingEnum;
use Core\Objects\DatabaseEntity\Attribute\Json;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Attribute\Multiple;
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
      } else if ($propertyTypeName === "array") {
        $json = self::getAttribute($property, Json::class);
        if ($json) {
          $this->columns[$propertyName] = new JsonColumn($columnName, $nullable, $defaultValue);
        } else {

          $multiple = self::getAttribute($property, Multiple::class);
          if (!$multiple) {
            $this->raiseError("Cannot persist class '$className': Property '$propertyName' has non persist-able type: $propertyTypeName. " .
              "Is the 'Multiple' attribute missing?");
          }

          try {
            $refClass = $multiple->getClassName();
            $requestedClass = new \ReflectionClass($refClass);
            if ($requestedClass->isSubclassOf(DatabaseEntity::class)) {
              $nmTableName = NMRelation::buildTableName($this->getTableName(), $requestedClass->getShortName());
              $nmRelation = $this->nmRelations[$nmTableName] ?? null;
              if (!$nmRelation) {
                $otherHandler = DatabaseEntity::getHandler($this->sql, $requestedClass);
                $otherNM = $otherHandler->getNMRelations();
                $nmRelation = $otherNM[$nmTableName] ?? (new NMRelation($this, $otherHandler));
                $this->nmRelations[$nmTableName] = $nmRelation;
              }

              $this->nmRelations[$nmTableName]->addProperty($this, $property);
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

  public static function getAttribute(\ReflectionProperty $property, string $attributeClass): ?object {
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

  public function getNMRelations(): array {
    return $this->nmRelations;
  }

  public function getColumnName(string $property): string {
    if ($property === "id") {
      return "$this->tableName.id";
    } else {
      return $this->tableName . "." . $this->columns[$property]->getName();
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
      foreach ($this->nmRelations as $nmRelation) {
        foreach ($nmRelation->getProperties($this) as $property) {
          $property->setAccessible(true);
          $property->setValue($entity, []);
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

  public function updateNM(DatabaseEntity $entity, ?array $properties = null): bool {
    if (empty($this->nmRelations)) {
      return true;
    }

    foreach ($this->nmRelations as $nmTable => $nmRelation) {

      $thisIdColumn = $nmRelation->getIdColumn($this);
      $thisTableName = $this->getTableName();
      $dataColumns = $nmRelation->getDataColumns();
      $otherHandler = $nmRelation->getOtherHandler($this);
      $refIdColumn = $nmRelation->getIdColumn($otherHandler);


      // delete from n:m table if no longer exists
      $doDelete = true;
      $deleteStatement = $this->sql->delete($nmTable)
        ->whereEq($thisIdColumn, $entity->getId());  // this condition is important

      if (!empty($dataColumns)) {
        $conditions = [];
        $doDelete = false;
        foreach ($dataColumns[$thisTableName] as $propertyName => $columnName) {
          if ($properties !== null && !in_array($propertyName, $properties)) {
            continue;
          }

          $property = $this->properties[$propertyName];
          $entityIds = array_keys($property->getValue($entity));
          if (!empty($entityIds)) {
            $conditions[] = new CondAnd(
              new CondBool($columnName),
              new CondNot(new CondIn(new Column($refIdColumn), $entityIds)),
            );
          }
        }

        if (!empty($conditions)) {
          $deleteStatement->where(new CondOr(...$conditions));
          $doDelete = true;
        }
      } else {
        $property = next($nmRelation->getProperties($this));
        if ($properties !== null && !in_array($property->getName(), $properties)) {
          $doDelete = false;
        } else {
          $entityIds = array_keys($property->getValue($entity));
          if (!empty($entityIds)) {
            $deleteStatement->where(
              new CondNot(new CondIn(new Column($refIdColumn), $entityIds))
            );
          }
        }
      }

      if ($doDelete) {
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
    foreach ($this->nmRelations as $nmTable => $nmRelation) {
      $otherHandler = $nmRelation->getOtherHandler($this);
      $thisIdColumn = $nmRelation->getIdColumn($this);
      $thisTableName = $this->getTableName();
      $refIdColumn = $nmRelation->getIdColumn($otherHandler);
      $dataColumns = $nmRelation->getDataColumns();

      $columns = [
        $thisIdColumn,
        $refIdColumn,
      ];

      if (!empty($dataColumns)) {
        $columns = array_merge($columns, array_values($dataColumns[$thisTableName]));
      }

      $statement = $this->sql->insert($nmTable, $columns);
      if ($ignoreExisting) {
        $statement->onDuplicateKeyStrategy(new UpdateStrategy($nmRelation->getAllColumns(), [
          $thisIdColumn => $entity->getId()
        ]));
      }

      $doInsert = false;
      foreach ($nmRelation->getProperties($this) as $property) {
        if ($properties !== null && !in_array($property->getName(), $properties)) {
          continue;
        }

        $property->setAccessible(true);
        $relEntities = $property->getValue($entity);
        foreach ($relEntities as $relEntity) {
          $relEntityId = (is_int($relEntity) ? $relEntity : $relEntity->getId());
          $nmRow = [$entity->getId(), $relEntityId];
          if (!empty($dataColumns)) {
            foreach (array_keys($dataColumns[$thisTableName]) as $propertyName) {
              $nmRow[] = $property->getName() === $propertyName;
            }
          }
          $statement->addRow(...$nmRow);
          $doInsert = true;
        }
      }

      if ($doInsert) {
        $success = $statement->execute() && $success;
      }
    }

    return $success;
  }

  public function fetchNMRelations(array $entities, bool $recursive = false) {

    if ($recursive) {
      foreach ($entities as $entity) {
        foreach ($this->relations as $propertyName => $relHandler) {
          $property = $this->properties[$propertyName];
          if ($property->isInitialized($entity) || true) {
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
    foreach ($this->nmRelations as $nmTable => $nmRelation) {
      $otherHandler = $nmRelation->getOtherHandler($this);

      $thisIdColumn = $nmRelation->getIdColumn($this);
      $thisProperties = $nmRelation->getProperties($this);
      $thisTableName = $this->getTableName();

      $refIdColumn = $nmRelation->getIdColumn($otherHandler);
      $refProperties = $nmRelation->getProperties($otherHandler);
      $refTableName = $otherHandler->getTableName();

      $dataColumns = $nmRelation->getDataColumns();

      $relEntityQuery = DatabaseEntityQuery::fetchAll($otherHandler)
        ->addJoin(new InnerJoin($nmTable, "$nmTable.$refIdColumn", "$refTableName.id"))
        ->where(new CondIn(new Column($thisIdColumn), $entityIds));

      $relEntityQuery->addSelectValue(new Column($thisIdColumn));
      foreach ($dataColumns as $tableDataColumns) {
        foreach ($tableDataColumns as $columnName) {
          $relEntityQuery->addSelectValue(new Column($columnName));
        }
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
        $mappings = [
          [$refProperties, $refTableName, $relEntity, $thisEntity],
          [$thisProperties, $thisTableName, $thisEntity, $relEntity],
        ];

        foreach ($mappings as $mapping) {
          list($properties, $tableName, $targetEntity, $entityToAdd) = $mapping;
          foreach ($properties as $propertyName => $property) {
            $addToProperty = empty($dataColumns);
            if (!$addToProperty) {
              $columnName = $dataColumns[$tableName][$propertyName] ?? null;
              $addToProperty = ($columnName && $this->sql->parseBool($row[$columnName]));
            }

            if ($addToProperty) {
              $targetArray = $property->getValue($targetEntity);
              $targetArray[$entityToAdd->getId()] = $entityToAdd;
              $property->setValue($targetEntity, $targetArray);
            }
          }
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