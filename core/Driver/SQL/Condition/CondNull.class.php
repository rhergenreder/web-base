<?php

namespace Driver\SQL\Condition;

class CondNull extends Condition {

  private string $column;

  public function __construct(string $col) {
    $this->column = $col;
  }

  public function getColumn(): string { return $this->column; }
}