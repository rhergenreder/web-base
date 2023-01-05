<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\SQL;

class Count extends Alias {
  public function __construct(mixed $value = "*", string $alias = "count") {
    parent::__construct($value, $alias);
  }

  function addValue(SQL $sql, array &$params): string {
    $value = $this->getValue();
    if (is_string($value)) {
      if ($value === "*") {
        return "COUNT(*)";
      } else {
        return "COUNT(" . $sql->columnName($value) . ")";
      }
    } else {
      return "COUNT(" . $sql->addValue($value, $params) . ")";
    }
  }
}