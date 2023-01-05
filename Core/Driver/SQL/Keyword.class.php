<?php

namespace Core\Driver\SQL;

use Core\Driver\SQL\Expression\Expression;

// Unsafe sql
class Keyword extends Expression {

  private string $value;

  public function __construct(string $value) {
    $this->value = $value;
  }

  public function getValue(): string { return $this->value; }

  function getExpression(SQL $sql, array &$params): string {
    return $this->value;
  }
}