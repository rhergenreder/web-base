<?php

namespace Core\Driver\SQL\Constraint;

use Core\Driver\SQL\Strategy\Strategy;

class ForeignKey extends Constraint {

  private string $referencedTable;
  private string $referencedColumn;
  private ?Strategy $strategy;

  public function __construct(string $columnName, string $refTable, string $refColumn, ?Strategy $strategy = NULL) {
    parent::__construct($columnName);
    $this->referencedTable = $refTable;
    $this->referencedColumn = $refColumn;
    $this->strategy = $strategy;
  }

  public function getReferencedTable(): string { return $this->referencedTable; }
  public function getReferencedColumn(): string { return $this->referencedColumn; }
  public function onDelete(): ?Strategy { return $this->strategy; }
}