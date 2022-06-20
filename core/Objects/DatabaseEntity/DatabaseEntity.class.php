<?php

namespace Objects\DatabaseEntity;

use Driver\SQL\Condition\Compare;
use Driver\SQL\Condition\Condition;
use Driver\SQL\SQL;

abstract class DatabaseEntity {

  private static array $handlers = [];
  protected ?int $id;

  public function __construct(?int $id = null) {
    $this->id = $id;
  }

  public abstract function jsonSerialize(): array;

  public function preInsert(array &$row) { }
  public function postFetch(SQL $sql, array $row) { }

  public static function fromRow(SQL $sql, array $row): static {
    $handler = self::getHandler($sql);
    return $handler->entityFromRow($row);
  }

  public static function newInstance(\ReflectionClass $reflectionClass, array $row) {
    return $reflectionClass->newInstanceWithoutConstructor();
  }

  public static function find(SQL $sql, int $id, bool $fetchEntities = false, bool $fetchRecursive = false): static|bool|null {
    $handler = self::getHandler($sql);
    if ($fetchEntities) {
      return DatabaseEntityQuery::fetchOne(self::getHandler($sql))
        ->where(new Compare($handler->getTableName() . ".id", $id))
        ->fetchEntities($fetchRecursive)
        ->execute();
    } else {
      return $handler->fetchOne($id);
    }
  }

  public static function exists(SQL $sql, int $id): bool {
    $handler = self::getHandler($sql);
    $res = $sql->select($sql->count())
      ->from($handler->getTableName())
      ->where(new Compare($handler->getTableName() . ".id", $id))
      ->execute();

    return $res !== false && $res[0]["count"] !== 0;
  }

  public static function findBuilder(SQL $sql): DatabaseEntityQuery {
    return DatabaseEntityQuery::fetchOne(self::getHandler($sql));
  }

  public static function findAll(SQL $sql, ?Condition $condition = null): ?array {
    $handler = self::getHandler($sql);
    return $handler->fetchMultiple($condition);
  }

  public static function findAllBuilder(SQL $sql): DatabaseEntityQuery {
    return DatabaseEntityQuery::fetchAll(self::getHandler($sql));
  }

  public function save(SQL $sql, ?array $columns = null): bool {
    $handler = self::getHandler($sql);
    $res = $handler->insertOrUpdate($this, $columns);
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
}