<?php

namespace Driver\SQL\Expression;

use Driver\SQL\Condition\Condition;

class Sum extends Expression {

  private $value;
  private string $alias;

  public function __construct($value, string $alias) {
    $this->value = $value;
    $this->alias = $alias;
  }

  public function getValue() { return $this->value; }
  public function getAlias(): string { return $this->alias; }

}