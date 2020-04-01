<?php

namespace Driver\SQL\Condition;

class CondAnd extends Condition {

  private $conditions;

  public function __construct(...$conditions) {
    $this->conditions = $conditions;
  }

  public function getConditions() { return $this->conditions; }
}

?>
