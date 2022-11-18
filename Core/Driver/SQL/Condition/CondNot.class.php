<?php

namespace Core\Driver\SQL\Condition;

class CondNot extends Condition {

  private $expression; // string or condition

  public function __construct($expression) {
    $this->expression = $expression;
  }

  public function getExpression() {
    return $this->expression;
  }
}