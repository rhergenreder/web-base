<?php

namespace Driver\SQL\Condition;

class CondIn extends Condition {

  private string $column;
  private $expression;

  public function __construct(string $column, $expression) {
    $this->column = $column;
    $this->expression = $expression;
  }

  public function getColumn() { return $this->column; }
  public function getExpression() { return $this->expression; }
}