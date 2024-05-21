<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\MySQL;
use Core\Driver\SQL\PostgreSQL;
use Core\Driver\SQL\SQL;
use Exception;

class DateAdd extends Expression {

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

  function getExpression(SQL $sql, array &$params): string {
    if ($sql instanceof MySQL) {
      $lhs = $sql->addValue($this->getLHS(), $params);
      $rhs = $sql->addValue($this->getRHS(), $params);
      $unit = $this->getUnit();
      return "DATE_ADD($lhs, INTERVAL $rhs $unit)";
    } else if ($sql instanceof PostgreSQL) {
      $lhs = $sql->addValue($this->getLHS(), $params);
      $rhs = $sql->addValue($this->getRHS(), $params);
      $unit = $this->getUnit();

      if ($this->getRHS() instanceof Column) {
        $rhs = "$rhs * INTERVAL '1 $unit'";
      } else {
        $rhs = "$rhs $unit";
      }

      return "$lhs - $rhs";
    } else {
      throw new Exception("DateAdd Not implemented for driver type: " . get_class($sql));
    }
  }
}