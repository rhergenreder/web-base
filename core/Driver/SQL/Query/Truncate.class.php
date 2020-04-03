<?php

namespace Driver\SQL\Query;

class Truncate extends Query {

  private string $tableName;

  public function __construct($sql, $name) {
    parent::__construct($sql);
    $this->tableName = $name;
  }

  public function execute() {
    return $this->sql->executeTruncate($this);
  }

  public function getTable() { return $this->tableName; }
}