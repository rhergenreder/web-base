<?php

namespace Core\Driver\SQL\Join;

class InnerJoin extends Join {
  public function __construct(string $table, string $columnA, string $columnB, ?string $tableAlias = null, array $conditions = []) {
    parent::__construct("INNER", $table, $columnA, $columnB, $tableAlias, $conditions);
  }
}