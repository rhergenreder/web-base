<?php

namespace Driver\SQL\Query;

use Driver\SQL\Condition\CondOr;
use Driver\SQL\SQL;

class Delete extends Query {

  private string $table;
  private array $conditions;

  public function __construct(SQL $sql, string $table) {
    parent::__construct($sql);
    $this->table = $table;
    $this->conditions = array();
  }

  public function where(...$conditions): Delete {
    $this->conditions[] = (count($conditions) === 1 ? $conditions : new CondOr($conditions));
    return $this;
  }

  public function execute(): bool {
    return $this->sql->executeDelete($this);
  }

  public function getTable(): string { return $this->table; }
  public function getConditions(): array { return $this->conditions; }
}
