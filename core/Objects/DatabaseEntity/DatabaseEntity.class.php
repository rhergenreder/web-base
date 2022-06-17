<?php

namespace Objects\DatabaseEntity;

use Driver\Logger\Logger;
use Driver\SQL\Condition\Condition;
use Driver\SQL\SQL;

abstract class DatabaseEntity {

  private static array $handlers = [];
  private ?int $id;

  public function __construct() {
    $this->id = null;
  }

  public static function find(SQL $sql, int $id): ?DatabaseEntity {
    $handler = self::getHandler();
    return $handler->fetchOne($sql, $id);
  }

  public static function findAll(SQL $sql, ?Condition $condition): ?array {
    $handler = self::getHandler();
    return $handler->fetchMultiple($sql, $condition);
  }

  public function save(SQL $sql): bool {
    $handler = self::getHandler();
    $res = $handler->insertOrUpdate($sql, $this);
    if ($res === false) {
      return false;
    } else if ($this->id === null) {
      $this->id = $res;
    }

    return true;
  }

  public function delete(SQL $sql): bool {
    $handler = self::getHandler();
    if ($this->id === null) {
      $className = $handler->getReflection()->getName();
      (new Logger("DatabaseEntity", $sql))->error("Cannot delete entity of class '$className' without id");
      return false;
    }

    return $handler->delete($sql, $this->id);
  }

  public static function getHandler($obj_or_class = null): DatabaseEntityHandler {

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
      $handler = new DatabaseEntityHandler($class);
      self::$handlers[$class->getShortName()] = $handler;
    }

    return $handler;
  }

  public function getId(): ?int {
    return $this->id;
  }
}