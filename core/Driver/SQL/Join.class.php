<?php

namespace Driver\SQL;

class Join {

  private string $type;
  private string $table;
  private string $columnA;
  private string $columnB;
  private $tableAlias;

  public function __construct($type, $table, $columnA, $columnB, $tableAlias=null) {
    $this->type = $type;
    $this->table = $table;
    $this->columnA = $columnA;
    $this->columnB = $columnB;
    $this->tableAlias = $tableAlias;
  }

  public function getType() { return $this->type; }
  public function getTable() { return $this->table; }
  public function getColumnA() { return $this->columnA; }
  public function getColumnB() { return $this->columnB; }
  public function getTableAlias() { return $this->tableAlias; }

}