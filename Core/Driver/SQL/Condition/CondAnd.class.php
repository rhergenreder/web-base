<?php

namespace Core\Driver\SQL\Condition;

use Core\Driver\SQL\SQL;

class CondAnd extends Condition {

  private array $conditions;

  public function __construct(...$conditions) {
    $this->conditions = $conditions;
  }

  public function getConditions(): array { return $this->conditions; }

  function getExpression(SQL $sql, array &$params): string {
    $conditions = array();
    foreach($this->getConditions() as $cond) {
      $conditions[] = $sql->addValue($cond, $params);
    }
    return "(" . implode(" AND ", $conditions) . ")";
  }
}