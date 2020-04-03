<?php

namespace Driver\SQL\Query;

use Driver\SQL\Condition\CondOr;

class Update extends Query {

  private array $values;
  private string $table;
  private array $conditions;

  public function __construct($sql, $table) {
    parent::__construct($sql);
    $this->values = array();
    $this->table = $table;
    $this->conditions = array();
  }

  public function where(...$conditions) {
    $this->conditions[] = (count($conditions) === 1 ? $conditions : new CondOr($conditions));
    return $this;
  }

  public function set($key, $val) {
    $this->values[$key] = $val;
    return $this;
  }

  public function execute() {
    return $this->sql->executeUpdate($this);
  }

  public function getTable() { return $this->table; }
  public function getConditions() { return $this->conditions; }
  public function getValues() { return $this->values; }
}