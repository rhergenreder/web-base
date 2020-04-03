<?php

namespace Driver\SQL\Condition;

class CondOr extends Condition {

  private array $conditions;

  public function __construct(...$conditions) {
    $this->conditions = $conditions;
  }

  public function getConditions() { return $this->conditions; }
}