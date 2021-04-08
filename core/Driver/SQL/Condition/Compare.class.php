<?php

namespace Driver\SQL\Condition;

class Compare extends Condition {

  private string $operator;
  private string $column;
  private $value;

  public function __construct(string $col, $val, string $operator = '=') {
    $this->operator = $operator;
    $this->column = $col;
    $this->value = $val;
  }

  public function getColumn(): string { return $this->column; }
  public function getValue() { return $this->value; }
  public function getOperator(): string { return $this->operator; }

}