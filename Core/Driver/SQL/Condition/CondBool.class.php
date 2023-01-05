<?php

namespace Core\Driver\SQL\Condition;

use Core\Driver\SQL\SQL;

class CondBool extends Condition {

  private $value;

  public function __construct($val) {
    $this->value = $val;
  }

  public function getValue() { return $this->value; }

  function getExpression(SQL $sql, array &$params): string {
    if (is_string($this->value)) {
      return $sql->columnName($this->value);
    } else {
      return $sql->addValue($this->value);
    }
  }
}