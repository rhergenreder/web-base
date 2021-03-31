<?php

namespace Driver\SQL\Query;

use Driver\SQL\Condition\CondOr;
use Driver\SQL\Join;

class Select extends Query {

  private array $columns;
  private array $tables;
  private array $conditions;
  private array $joins;
  private array $orderColumns;
  private array $groupColumns;
  private bool $sortAscending;
  private int $limit;
  private int $offset;

  public function __construct($sql, ...$columns) {
    parent::__construct($sql);
    $this->columns = (!empty($columns) && is_array($columns[0])) ? $columns[0] : $columns;
    $this->tables = array();
    $this->conditions = array();
    $this->joins = array();
    $this->orderColumns = array();
    $this->groupColumns = array();
    $this->limit = 0;
    $this->offset = 0;
    $this->sortAscending = true;
  }

  public function from(...$tables) {
    $this->tables = array_merge($this->tables, $tables);
    return $this;
  }

  public function where(...$conditions) {
    $this->conditions[] = (count($conditions) === 1 ? $conditions : new CondOr($conditions));
    return $this;
  }

  public function innerJoin($table, $columnA, $columnB, $tableAlias=null) {
    $this->joins[] = new Join("INNER", $table, $columnA, $columnB, $tableAlias);
    return $this;
  }

  public function leftJoin($table, $columnA, $columnB, $tableAlias=null) {
    $this->joins[] = new Join("LEFT", $table, $columnA, $columnB, $tableAlias);
    return $this;
  }

  public function groupBy(...$columns) {
    $this->groupColumns = $columns;
    return $this;
  }

  public function orderBy(...$columns) {
    $this->orderColumns = $columns;
    return $this;
  }

  public function ascending() {
    $this->sortAscending = true;
    return $this;
  }

  public function descending() {
    $this->sortAscending = false;
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
  public function isOrderedAscending() { return $this->sortAscending; }
  public function getOrderBy() { return $this->orderColumns; }
  public function getLimit() { return $this->limit; }
  public function getOffset() { return $this->offset; }
  public function getGroupBy() { return $this->groupColumns; }

}