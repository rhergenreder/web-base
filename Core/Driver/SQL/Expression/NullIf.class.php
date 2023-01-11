<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\SQL;

class NullIf extends Expression {

  private mixed $lhs;
  private mixed $rhs;

  public function __construct(mixed $lhs, mixed $rhs) {
    $this->lhs = $lhs;
    $this->rhs = $rhs;
  }

  function getExpression(SQL $sql, array &$params): string {
    $lhs = $sql->addValue($this->lhs, $params);
    $rhs = $sql->addValue($this->rhs, $params);
    return "NULLIF($lhs, $rhs)";
  }
}