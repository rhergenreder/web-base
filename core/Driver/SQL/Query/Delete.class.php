<?php

namespace Driver\SQL\Query;

use Driver\SQL\Condition\CondOr;

class Delete extends Query {

  private string $table;
  private array $conditions;

  public function __construct($sql, $table) {
    parent::__construct($sql);
    $this->table = $table;
    $this->conditions = array();
  }

  public function where(...$conditions) {
    $this->conditions[] = (count($conditions) === 1 ? $conditions : new CondOr($conditions));
    return $this;
  }

  public function execute() {
    return $this->sql->executeDelete($this);
  }

  public function getTable() { return $this->table; }
  public function getConditions() { return $this->conditions; }
}
