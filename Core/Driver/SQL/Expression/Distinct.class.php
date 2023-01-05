<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\SQL;

class Distinct extends Expression {

  private mixed $value;

  public function __construct(mixed $value) {
    $this->value = $value;
  }

  public function getValue(): mixed {
    return $this->value;
  }

  function getExpression(SQL $sql, array &$params): string {
    return "DISTINCT(" . $sql->addValue($this->getValue(), $params) . ")";
  }
}