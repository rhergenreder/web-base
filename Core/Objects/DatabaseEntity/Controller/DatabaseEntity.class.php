<?php

namespace Core\Objects\DatabaseEntity\Controller;

use ArrayAccess;
use Core\Driver\SQL\Condition\Condition;
use Core\Driver\SQL\Expression\Count;
use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\Transient;
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

  public abstract function jsonSerialize(): array;

  public function preInsert(array &$row) { }
  public function postFetch(SQL $sql, array $row) { }

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

  // TODO: rather take property names here instead of $columns? and translate then using DatabaseEntityHandler::columns[$propertyName]
  public function save(SQL $sql, ?array $columns = null, bool $saveNM = false): bool {
    $handler = self::getHandler($sql);
    $res = $handler->insertOrUpdate($this, $columns, $saveNM);
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

  public static function getHandler(SQL $sql, $obj_or_class = null): DatabaseEntityHandler {

    if (!$obj_or_class) {
      $obj_or_class = get_called_class();
    }

    if (!($obj_or_class instanceof \ReflectionClass)) {
      $class = new \ReflectionClass($obj_or_class);
    } else {
      $class = $obj_or_class;
    }

    // if we are in an extending context, get the database handler for the root entity,
    // as we do not persist attributes of the inheriting class
    while ($class->getParentClass()->getName() !== DatabaseEntity::class) {
      $class = $class->getParentClass();
    }

    $handler = self::$handlers[$class->getShortName()] ?? null;
    if (!$handler) {
      $handler = new DatabaseEntityHandler($sql, $class);
      self::$handlers[$class->getShortName()] = $handler;
    }

    return $handler;
  }

  public function getId(): ?int {
    return $this->id;
  }

  public static function count(SQL $sql, ?Condition $condition = null): int|bool {
    $handler = self::getHandler($sql);
    $query = $sql->select(new Count())
      ->from($handler->getTableName());

    if ($condition) {
      $query->where($condition);
    }

    $res = $query->execute();

    if (!empty($res)) {
      return $res[0]["count"];
    }

    return false;
  }
}