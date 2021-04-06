<?php

namespace Driver\SQL\Condition;

class CondAnd extends Condition {

  private array $conditions;

  public function __construct(...$conditions) {
    $this->conditions = $conditions;
  }

  public function getConditions(): array { return $this->conditions; }
}