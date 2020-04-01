<?php

namespace Driver\SQL\Constraint;

abstract class Constraint {

  private $columnName;

  public function __construct($columnName) {
    $this->columnName = $columnName;
  }

  public function getColumnName() { return $this->columnName; }
};

?>
