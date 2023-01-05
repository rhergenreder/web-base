<?php

namespace Core\Driver\SQL\Condition;

use Core\Driver\SQL\Query\Select;
use Core\Driver\SQL\SQL;

class Exists extends Condition {

    private Select $subQuery;

    public function __construct(Select $subQuery) {
        $this->subQuery = $subQuery;
    }

    public function getSubQuery(): Select {
        return $this->subQuery;
    }

  function getExpression(SQL $sql, array &$params): string {
    return "EXISTS(" .$this->getSubQuery()->build($params) . ")";
  }
}