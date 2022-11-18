<?php

namespace Core\Driver\SQL\Condition;

class CondOr extends Condition {

  private array $conditions;

  public function __construct(...$conditions) {
    $this->conditions = (!empty($conditions) && is_array($conditions[0])) ? $conditions[0] : $conditions;
  }

  public function getConditions(): array { return $this->conditions; }
}