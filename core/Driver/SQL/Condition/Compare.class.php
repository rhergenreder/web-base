<?php

namespace Driver\SQL\Condition;

class Compare extends Condition {

  public function __construct($col, $val, $operator='=') {
    $this->operator = $operator;
    $this->column = $col;
    $this->value = $val;
  }

  public function getColumn() { return $this->column; }
  public function getValue() { return $this->value; }
  public function getOperator() { return $this->operator; }

}

?>
