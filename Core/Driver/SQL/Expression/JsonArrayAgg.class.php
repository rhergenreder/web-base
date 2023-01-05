<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\MySQL;
use Core\Driver\SQL\PostgreSQL;
use Core\Driver\SQL\SQL;
use Exception;

class JsonArrayAgg extends Expression {

  private mixed $value;

  public function __construct(mixed $value) {
    $this->value = $value;
  }

  public function getExpression(SQL $sql, array &$params): string {
    $value = is_string($this->value) ? new Column($this->value) : $this->value;
    $value = $sql->addValue($value, $params);
    if ($sql instanceof MySQL) {
      return "JSON_ARRAYAGG($value)";
    } else if ($sql instanceof PostgreSQL) {
      return "JSON_AGG($value)";
    } else {
      throw new Exception("JsonArrayAgg not implemented for driver type: " . get_class($sql));
    }
  }
}