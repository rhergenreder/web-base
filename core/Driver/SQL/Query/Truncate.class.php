<?php

namespace Driver\SQL\Query;

use Driver\SQL\SQL;

class Truncate extends Query {

  private string $tableName;

  public function __construct(SQL $sql, string $name) {
    parent::__construct($sql);
    $this->tableName = $name;
  }

  public function execute(): bool {
    return $this->sql->executeTruncate($this);
  }

  public function getTable(): string { return $this->tableName; }
}