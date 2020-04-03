<?php

namespace Driver\SQL\Constraint;

use Driver\SQL\Strategy\Strategy;

class ForeignKey extends Constraint {

  private string $referencedTable;
  private string $referencedColumn;
  private ?Strategy $strategy;

  public function __construct($name, $refTable, $refColumn, $strategy = NULL) {
    parent::__construct($name);
    $this->referencedTable = $refTable;
    $this->referencedColumn = $refColumn;
    $this->strategy = $strategy;
  }

  public function getReferencedTable() { return $this->referencedTable; }
  public function getReferencedColumn() { return $this->referencedColumn; }
  public function onDelete() { return $this->strategy; }
}