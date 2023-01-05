<?php

namespace Core\Driver\SQL\Condition;

use Core\Driver\SQL\SQL;

class Compare extends Condition {

  private string $operator;
  private string $column;
  private $value;

  public function __construct(string $col, $val, string $operator = '=') {
    $this->operator = $operator;
    $this->column = $col;
    $this->value = $val;
  }

  public function getColumn(): string { return $this->column; }
  public function getValue() { return $this->value; }
  public function getOperator(): string { return $this->operator; }

  function getExpression(SQL $sql, array &$params): string {

    if ($this->value === null) {
      if ($this->operator === "=") {
        return $sql->columnName($this->column) . " IS NULL";
      } else if ($this->operator === "!=") {
        return $sql->columnName($this->column) . " IS NOT NULL";
      }
    }

    return $sql->columnName($this->column) . $this->operator . $sql->addValue($this->value, $params);
  }
}