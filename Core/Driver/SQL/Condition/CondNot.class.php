<?php

namespace Core\Driver\SQL\Condition;

use Core\Driver\SQL\SQL;

class CondNot extends Condition {

  private mixed $expression; // string or condition

  public function __construct(mixed $expression) {
    $this->expression = $expression;
  }

  public function getExpression(SQL $sql, array &$params): string {
    if (is_string($this->expression)) {
      return "NOT " . $sql->columnName($this->expression);
    } else {
      return "NOT " . $sql->addValue($this->expression, $params);
    }
  }
}