<?php

namespace Core\Driver\SQL\Condition;

use Core\Driver\SQL\SQL;

class CondNull extends Condition {

  private string $column;

  public function __construct(string $col) {
    $this->column = $col;
  }

  public function getColumn(): string { return $this->column; }

  function getExpression(SQL $sql, array &$params): string {
    return $sql->columnName($this->getColumn()) . " IS NULL";
  }
}