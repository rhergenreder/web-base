<?php

namespace Core\Driver\SQL\Condition;

use Core\Driver\SQL\SQL;

class CondOr extends Condition {

  private array $conditions;

  public function __construct(...$conditions) {
    $this->conditions = (!empty($conditions) && is_array($conditions[0])) ? $conditions[0] : $conditions;
  }

  public function getConditions(): array { return $this->conditions; }

  function getExpression(SQL $sql, array &$params): string {
    $conditions = array();
    foreach($this->getConditions() as $cond) {
      $conditions[] = $sql->addValue($cond, $params);
    }
    return "(" . implode(" OR ", $conditions) . ")";
  }
}