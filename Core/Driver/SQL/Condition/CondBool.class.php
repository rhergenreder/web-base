<?php

namespace Core\Driver\SQL\Condition;

class CondBool extends Condition {

  private $value;

  public function __construct($val) {
    $this->value = $val;
  }

  public function getValue() { return $this->value; }

}