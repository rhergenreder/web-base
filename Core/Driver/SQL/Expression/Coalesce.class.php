<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\SQL;

class Coalesce extends Expression {

  private array $values;

  public function __construct(mixed ...$values) {
    $this->values = $values;
  }

  function getExpression(SQL $sql, array &$params): string {
    $values = implode(",", array_map(function ($value) use ($sql, &$params) {
      if (is_string($value)) {
        return $sql->columnName($value);
      } else {
        return $sql->addValue($value, $params);
      }
    }, $this->values));
    return "COALESCE($values)";
  }
}