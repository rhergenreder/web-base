<?php

namespace Driver\SQL\Condition;

class CondBool extends Condition {

  public function __construct($val) {
    $this->value = $val;
  }

  public function getValue() { return $this->value; }

}

?>
