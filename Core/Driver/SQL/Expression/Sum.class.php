<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\SQL;

class Sum extends Alias {

  public function __construct(mixed $value, string $alias) {
    parent::__construct($value, $alias);
  }

  protected function addValue(SQL $sql, array &$params): string {
    return "SUM(" . $sql->addValue($this->getValue(), $params) . ")";
  }
}