<?php

namespace Driver\SQL\Query;

class Select extends Query {

  private $columns;
  private $tables;
  private $conditions;
  private $joins;
  private $orderColumns;
  private $sortAscending;
  private $limit;
  private $offset;

  public function __construct($sql, ...$columns) {
    parent::__construct($sql);
    $this->columns = (!empty($columns) && is_array($columns[0])) ? $columns[0] : $columns;
    $this->tables = array();
    $this->conditions = array();
    $this->joins = array();
    $this->orderColumns = array();
    $this->limit = 0;
    $this->offset = 0;
    $this->sortAscending = true;
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

  public function orderBy(...$columns) {
    $this->orderColumns = $columns;
    return $this;
  }

  public function ascending() {
    $this->ascending = true;
    return $this;
  }

  public function descending() {
    $this->ascending = false;
    return $this;
  }

  public function limit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function offset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function execute() {
    return $this->sql->executeSelect($this);
  }

  public function getColumns() { return $this->columns; }
  public function getTables() { return $this->tables; }
  public function getConditions() { return $this->conditions; }
  public function getJoins() { return $this->joins; }
  public function isOrderedAscending() { return $this->ascending; }
  public function getOrderBy() { return $this->orderColumns; }
  public function getLimit() { return $this->limit; }
  public function getOffset() { return $this->offset; }

};

?>
