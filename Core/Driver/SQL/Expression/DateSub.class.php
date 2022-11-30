<?php

namespace Core\Driver\SQL\Expression;

class DateSub extends Expression {

  private Expression $lhs;
  private Expression $rhs;
  private string $unit;

  public function __construct(Expression $lhs, Expression $rhs, string $unit) {
    $this->lhs = $lhs;
    $this->rhs = $rhs;
    $this->unit = $unit;
  }

  public function getLHS(): Expression { return $this->lhs; }
  public function getRHS(): Expression { return $this->rhs; }
  public function getUnit(): string { return $this->unit; }

}