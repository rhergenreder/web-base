<?php

namespace Driver\SQL;

class Join {

  private $type;
  private $table;
  private $columnA;
  private $columnB;

  public function __construct($type, $table, $columnA, $columnB) {
    $this->tpye = $type;
    $this->table = $table;
    $this->columnA = $columnA;
    $this->columnB = $columnB;
  }

  public function getType() { return $this->type; }
  public function getTable() { return $this->table; }
  public function getColumnA() { return $this->columnA; }
  public function getColumnB() { return $this->columnB; }

}

?>
