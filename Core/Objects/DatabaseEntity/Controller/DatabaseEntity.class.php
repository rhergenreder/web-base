<?php

namespace Core\Objects\DatabaseEntity\Controller;

use ArrayAccess;
use Core\Driver\SQL\Condition\Condition;
use Core\Driver\SQL\Expression\Count;
use Core\Driver\SQL\SQL;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\Attribute\Transient;
use Core\Objects\DatabaseEntity\Attribute\Visibility;
use JsonSerializable;

abstract class DatabaseEntity implements ArrayAccess, JsonSerializable {

  protected static array $entityLogConfig = [
    "insert" => false,
    "update" => false,
    "delete" => false,
    "lifetime" => null,
  ];

  private static array $handlers = [];
  protected ?int $id;
  #[Transient] protected array $customData = [];

  public function __construct(?int $id = null) {
    $this->id = $id;
  }

  public function offsetExists(mixed $offset): bool {
    return property_exists($this, $offset) || array_key_exists($offset, $this->customData);
  }

  public function offsetGet(mixed $offset): mixed {
    if (property_exists($this, $offset)) {
      return $this->{$offset};
    } else {
      return $this->customData[$offset];
    }
  }

  public function offsetSet(mixed $offset, mixed $value): void {
    if (property_exists($this, $offset)) {
      $this->{$offset} = $value;
    } else {
      $this->customData[$offset] = $value;
    }
  }

  public function offsetUnset(mixed $offset): void {
    if (array_key_exists($offset, $this->customData)) {
      unset($this->customData[$offset]);
    }
  }

  public function jsonSerialize(?array $propertyNames = null): array {
    $properties = (new \ReflectionClass(get_called_class()))->getProperties();
    $ignoredProperties = ["entityLogConfig", "customData"];

    $jsonArray = [];
    foreach ($properties as $property) {
      $property->setAccessible(true);
      $propertyName = $property->getName();
      if (in_array($propertyName, $ignoredProperties)) {
        continue;
      }

      if (!empty($property->getAttributes(Transient::class))) {
        continue;
      }

      $visibility = DatabaseEntityHandler::getAttribute($property, Visibility::class);
      if ($visibility) {
        $visibilityType = $visibility->getType();
        if ($visibilityType === Visibility::NONE) {
          continue;
        } else if ($visibilityType === Visibility::BY_GROUP) {
          $currentUser = Context::instance()->getUser();
          $groups = $visibility->getGroups();
          if (!empty($groups)) {
            if (!$currentUser || empty(array_intersect(array_keys($currentUser->getGroups()), $groups))) {
              continue;
            }
          }
        }
      }

      if ($propertyNames === null || isset($propertyNames[$propertyName]) || in_array($propertyName, $propertyNames)) {
        if ($property->isInitialized($this)) {
          $value = $property->getValue($this);
          if ($value instanceof \DateTime) {
            $value = $value->getTimestamp();
          } else if ($value instanceof DatabaseEntity) {
            $subPropertyNames = $propertyNames[$propertyName] ?? null;
            $value = $value->jsonSerialize($subPropertyNames);
          }

          $jsonArray[$property->getName()] = $value;
        }
      }
    }

    return $jsonArray;
  }

  public static function toJsonArray(array $entities, ?array $properties = null): array {
    return array_map(function ($entity) use ($properties) {
        return $entity->jsonSerialize($properties);
      }, $entities);
  }

  public function preInsert(array &$row) { }
  public function postFetch(SQL $sql, array $row) { }
  public static function getPredefinedValues(): array { return []; }

  public static function fromRow(SQL $sql, array $row): static {
    $handler = self::getHandler($sql);
    return $handler->entityFromRow($row);
  }

  public static function newInstance(\ReflectionClass $reflectionClass) {
    return $reflectionClass->newInstanceWithoutConstructor();
  }

  public static function find(SQL $sql, int $id, bool $fetchEntities = false, bool $fetchRecursive = false): static|bool|null {
    $handler = self::getHandler($sql);
    if ($fetchEntities) {
      return DatabaseEntityQuery::fetchOne(self::getHandler($sql))
        ->whereEq($handler->getTableName() . ".id", $id)
        ->fetchEntities($fetchRecursive)
        ->execute();
    } else {
      return $handler->fetchOne($id);
    }
  }

  public static function exists(SQL $sql, int $id): bool {
    $handler = self::getHandler($sql);
    $res = $sql->select(new Count())
      ->from($handler->getTableName())
      ->whereEq($handler->getTableName() . ".id", $id)
      ->execute();

    return $res !== false && $res[0]["count"] !== 0;
  }

  public static function findBy(DatabaseEntityQuery $dbQuery): static|array|bool|null {
    return $dbQuery->execute();
  }

  public static function findAll(SQL $sql, ?Condition $condition = null): ?array {
    $handler = self::getHandler($sql);
    return $handler->fetchMultiple($condition);
  }

  public static function createBuilder(SQL $sql, bool $one): DatabaseEntityQuery {
    if ($one) {
      return DatabaseEntityQuery::fetchOne(self::getHandler($sql));
    } else {
      return DatabaseEntityQuery::fetchAll(self::getHandler($sql));
    }
  }

  public function save(SQL $sql, ?array $properties = null, bool $saveNM = false): bool {
    $handler = self::getHandler($sql);
    $res = $handler->insertOrUpdate($this, $properties, $saveNM);
    if ($res === false) {
      return false;
    } else if ($this->id === null) {
      $this->id = $res;
      $handler->insertNM($this);
    }

    return true;
  }

  public function insert(SQL $sql): bool {
    $handler = self::getHandler($sql);
    $res = $handler->insert($this);
    if ($res === false) {
      return false;
    } else if ($this->id === null) {
      $this->id = $res;
    }

    return true;
  }

  public function delete(SQL $sql): bool {
    $handler = self::getHandler($sql);
    if ($this->id === null) {
      $handler->getLogger()->error("Cannot delete entity without id");
      return false;
    }

    if ($handler->delete($this->id)) {
      $this->id = null;
      return true;
    }

    return false;
  }

  public static function getHandler(SQL $sql, $obj_or_class = null, $allowOverride = false): DatabaseEntityHandler {

    if (!$obj_or_class) {
      $obj_or_class = get_called_class();
    }

    if (!($obj_or_class instanceof \ReflectionClass)) {
      $class = new \ReflectionClass($obj_or_class);
    } else {
      $class = $obj_or_class;
    }

    if (!$allowOverride) {
      // if we are in an extending context, get the database handler for the root entity,
      // as we do not persist attributes of the inheriting class
      while ($class->getParentClass()->getName() !== DatabaseEntity::class) {
        $class = $class->getParentClass();
      }
    }

    $handler = self::$handlers[$class->getShortName()] ?? null;
    if (!$handler || $allowOverride) {
      $handler = new DatabaseEntityHandler($sql, $class);
      self::$handlers[$class->getShortName()] = $handler;
      $handler->init();
    }

    return $handler;
  }

  public function getId(): ?int {
    return $this->id;
  }

  public static function count(SQL $sql, ?Condition $condition = null, ?array $joins = []): int|bool {
    $handler = self::getHandler($sql);
    $query = $sql->select(new Count())
      ->from($handler->getTableName());

    if ($condition) {
      $query->where($condition);
    }

    if ($joins) {
      foreach ($joins as $join) {
        $query->addJoin($join);
      }
    }

    $res = $query->execute();

    if (!empty($res)) {
      return $res[0]["count"];
    }

    return false;
  }
}