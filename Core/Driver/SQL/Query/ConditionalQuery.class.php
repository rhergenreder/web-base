<?php

namespace Core\Driver\SQL\Query;

use Core\Driver\SQL\Condition\Compare;
use Core\Driver\SQL\Condition\CondBool;
use Core\Driver\SQL\Condition\CondNot;
use Core\Driver\SQL\Condition\CondOr;
use Core\Driver\SQL\SQL;

abstract class ConditionalQuery extends Query {

  private array $conditions;

  public function __construct(SQL $sql) {
    parent::__construct($sql);
    $this->conditions = [];
  }

  public function getWhereClause(array &$params): string {
    return $this->sql->getWhereClause($this->getConditions(), $params);
  }

  public function getConditions(): array {
    return $this->conditions;
  }


  public function where(...$conditions): static {
    $this->conditions[] = (count($conditions) === 1 ? $conditions : new CondOr($conditions));
    return $this;
  }

  public function whereEq(string $col, mixed $val): static {
    $this->conditions[] = new Compare($col, $val, "=");
    return $this;
  }

  public function whereNeq(string $col, mixed $val): static {
    $this->conditions[] = new Compare($col, $val, "!=");
    return $this;
  }

  public function whereGt(string $col, mixed $val): static {
    $this->conditions[] = new Compare($col, $val, ">");
    return $this;
  }

  public function whereLt(string $col, mixed $val): static {
    $this->conditions[] = new Compare($col, $val, "<");
    return $this;
  }

  public function whereTrue(string $col): static {
    $this->conditions[] = new CondBool($col);
    return $this;
  }

  public function whereFalse(string $col): static {
    $this->conditions[] = new CondNot(new CondBool($col));
    return $this;
  }
}