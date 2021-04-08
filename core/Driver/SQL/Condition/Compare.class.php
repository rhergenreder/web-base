<?php

namespace Driver\SQL\Condition;

class Compare extends Condition {

  private string $operator;
  private $lhs;
  private $value;

  public function __construct($col, $val, string $operator = '=') {
    $this->operator = $operator;
    $this->lhs = $col;
    $this->value = $val;
  }

  public function getLHS() { return $this->lhs; }
  public function getValue() { return $this->value; }
  public function getOperator(): string { return $this->operator; }

}