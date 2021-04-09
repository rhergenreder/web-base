<?php

namespace Driver\SQL\Query;

use Driver\SQL\SQL;

class Truncate extends Query {

  private string $tableName;

  public function __construct(SQL $sql, string $name) {
    parent::__construct($sql);
    $this->tableName = $name;
  }

  public function getTable(): string { return $this->tableName; }

  public function build(array &$params): ?string {
    return "TRUNCATE " . $this->sql->tableName($this->getTable());
  }
}