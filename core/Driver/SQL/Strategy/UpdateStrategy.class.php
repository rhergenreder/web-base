<?php

namespace Driver\SQL\Strategy;

class UpdateStrategy extends Strategy {

  private array $values;
  private array $conflictingColumns;

  public function __construct($conflictingColumns, $values) {
    $this->conflictingColumns = $conflictingColumns;
    $this->values = $values;
  }

  public function getConflictingColumns() {
    return $this->conflictingColumns;
  }

  public function getValues() { return $this->values; }
}