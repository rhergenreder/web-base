<?php

namespace Driver\SQL\Query;

class Select extends Query {

  private $columns;
  private $tables;
  private $conditions;
  private $joins;

  public function __construct($sql, ...$columns) {
    parent::__construct($sql);
    $this->columns = (!empty($columns) && is_array($columns[0])) ? $columns[0] : $columns;
    $this->tables = array();
    $this->conditions = array();
    $this->joins = array();
  }

  public function from(...$tables) {
    $this->tables = array_merge($this->tables, $tables);
    return $this;
  }

  public function where(...$conditions) {
    $this->conditions = array_merge($this->conditions, $conditions);
    return $this;
  }

  public function innerJoin($table, $columnA, $columnB) {
    $this->joins[] = new \Driver\SQL\Join("INNER", $table, $columnA, $columnB);
    return $this;
  }

  public function leftJoin($table, $columnA, $columnB) {
    $this->joins[] = new \Driver\SQL\Join("LEFT", $table, $columnA, $columnB);
    return $this;
  }

  public function execute() {
    return $this->sql->executeSelect($this);
  }

  public function getColumns() { return $this->columns; }
  public function getTables() { return $this->tables; }
  public function getConditions() { return $this->conditions; }
  public function getJoins() { return $this->joins; }
};

?>
