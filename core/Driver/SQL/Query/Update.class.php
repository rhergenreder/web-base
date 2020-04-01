<?php

namespace Driver\SQL\Query;

class Update extends Query {

  private $values;
  private $table;
  private $conditions;

  public function __construct($sql, $table) {
    parent::__construct($sql);
    $this->values = array();
    $this->table = $table;
    $this->conditions = array();
  }

  public function where(...$conditions) {
    $this->conditions = array_merge($this->conditions, $conditions);
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
};

?>
