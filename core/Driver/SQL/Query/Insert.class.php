<?php

namespace Driver\SQL\Query;

use Driver\SQL\Strategy\Strategy;

class Insert extends Query {

  private string $tableName;
  private array $columns;
  private array $rows;
  private ?Strategy $onDuplicateKey;
  private ?string $returning;

  public function __construct($sql, $name, $columns=array()) {
    parent::__construct($sql);
    $this->tableName = $name;
    $this->columns = $columns;
    $this->rows = array();
    $this->onDuplicateKey = NULL;
    $this->returning = NULL;
  }

  public function addRow(...$values) {
    $this->rows[] = $values;
    return $this;
  }

  public function onDuplicateKeyStrategy($strategy) {
    $this->onDuplicateKey = $strategy;
    return $this;
  }

  public function returning($column) {
    $this->returning = $column;
    return $this;
  }

  public function execute() {
    return $this->sql->executeInsert($this);
  }

  public function getTableName() { return $this->tableName; }
  public function getColumns() { return $this->columns; }
  public function getRows() { return $this->rows; }
  public function onDuplicateKey() { return $this->onDuplicateKey; }
  public function getReturning() { return $this->returning; }
}