<?php

namespace Driver\SQL\Constraint;

abstract class Constraint {

  private array $columnNames;

  public function __construct($columnNames) {
    $this->columnNames = (!is_array($columnNames) ? array($columnNames) : $columnNames);
  }

  public function getColumnNames() { return $this->columnNames; }
}