<?php

namespace Driver\SQL\Query;

class Insert extends Query {

  private $tableName;
  private $columns;
  private $rows;
  private $onDuplicateKey;

  public function __construct($sql, $name, $columns=array()) {
    parent::__construct($sql);
    $this->tableName = $name;
    $this->columns = $columns;
    $this->rows = array();
    $this->onDuplicateKey = NULL;
  }

  public function addRow(...$values) {
    $this->rows[] = $values;
    return $this;
  }

  public function onDuplicateKeyStrategy($strategy) {
    $this->onDuplicateKey = $strategy;
    return $this;
  }

  public function execute() {
    return $this->sql->executeInsert($this);
  }

  public function getTableName() { return $this->tableName; }
  public function getColumns() { return $this->columns; }
  public function getRows() { return $this->rows; }
  public function onDuplicateKey() { return $this->onDuplicateKey; }
};

?>
