<?php

namespace Core\Driver\SQL\Join;

class LeftJoin extends Join {
  public function __construct(string $table, string $columnA, string $columnB, ?string $tableAlias = null, array $conditions = []) {
    parent::__construct("LEFT", $table, $columnA, $columnB, $tableAlias, $conditions);
  }
}