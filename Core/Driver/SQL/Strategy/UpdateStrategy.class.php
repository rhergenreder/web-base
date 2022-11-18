<?php

namespace Core\Driver\SQL\Strategy;

class UpdateStrategy extends Strategy {

  private array $values;
  private array $conflictingColumns;

  public function __construct(array $conflictingColumns, array $values) {
    $this->conflictingColumns = $conflictingColumns;
    $this->values = $values;
  }

  public function getConflictingColumns(): array {
    return $this->conflictingColumns;
  }

  public function getValues(): array {
    return $this->values;
  }
}