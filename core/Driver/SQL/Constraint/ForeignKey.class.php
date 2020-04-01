<?php

namespace Driver\SQL\Constraint;

class ForeignKey extends Constraint {

  private $referencedTable;
  private $referencedColumn;
  private $strategy;

  public function __construct($name, $refTable, $refColumn, $strategy = NULL) {
    parent::__construct($name);
    $this->referencedTable = $refTable;
    $this->referencedColumn = $refColumn;
    $this->strategy = $strategy;
  }

  public function getReferencedTable() { return $this->referencedTable; }
  public function getReferencedColumn() { return $this->referencedColumn; }
  public function onDelete() { return $this->strategy; }
};

?>
