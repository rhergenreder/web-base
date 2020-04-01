<?php

namespace Driver\SQL\Query;

class Delete extends Query {

  private $table;
  private $conditions;

  public function __construct($sql, $table) {
    parent::__construct($sql);
    $this->table = $table;
    $this->conditions = array();
  }

  public function where(...$conditions) {
    $this->conditions = array_merge($this->conditions, $conditions);
    return $this;
  }

  public function execute() {
    return $this->sql->executeDelete($this);
  }

  public function getTable() { return $this->table; }
  public function getConditions() { return $this->conditions; }
};

?>
