<?php


namespace Driver\Sql\Condition;


class CondNull extends Condition {

  private string $column;

  public function __construct(string $col) {
    $this->column = $col;
  }

  public function getColumn() { return $this->column; }
}