<?php

namespace Core\Driver\SQL;

use Core\Driver\SQL\Expression\Expression;

class Keyword extends Expression {

  private string $value;

  public function __construct(string $value) {
    $this->value = $value;
  }

  public function getValue(): string { return $this->value; }

}