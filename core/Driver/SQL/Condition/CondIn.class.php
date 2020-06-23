<?php

namespace Driver\SQL\Condition;

use Driver\SQL\Column\Column;

class CondIn extends Condition {

  private string $column;
  private array $values;

  public function __construct(string $column, array $values) {
    $this->column = $column;
    $this->values = $values;
  }

  public function getColumn() { return $this->column; }
  public function getValues() { return $this->values; }
}