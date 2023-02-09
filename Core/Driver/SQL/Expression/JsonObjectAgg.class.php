<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\MySQL;
use Core\Driver\SQL\PostgreSQL;
use Core\Driver\SQL\SQL;
use Exception;

class JsonObjectAgg extends Expression {

  private mixed $key;
  private mixed $value;

  public function __construct(mixed $key, mixed $value) {
    $this->key = $key;
    $this->value = $value;
  }

  public function getExpression(SQL $sql, array &$params): string {
    $value = is_string($this->value) ? new Column($this->value) : $this->value;
    $value = $sql->addValue($value, $params);
    $key = is_string($this->key) ? new Column($this->key) : $this->key;
    $key = $sql->addValue($key, $params);
    if ($sql instanceof MySQL) {
      return "JSON_OBJECTAGG($key, $value)";
    } else if ($sql instanceof PostgreSQL) {
      return "JSON_OBJECT_AGG($value)";
    } else {
      throw new Exception("JsonObjectAgg not implemented for driver type: " . get_class($sql));
    }
  }
}