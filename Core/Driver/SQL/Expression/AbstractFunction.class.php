<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\SQL;

abstract class AbstractFunction extends Expression {

  private string $functionName;
  private mixed $value;

  public function __construct(string $functionName, mixed $value) {
    $this->functionName = $functionName;
    $this->value = $value;
  }

  public function getExpression(SQL $sql, array &$params): string {
    return $this->functionName . "(" . $sql->addValue($this->getValue(), $params) . ")";
  }

  public function getFunctionName(): string {
    return $this->functionName;
  }

  public function getValue(): mixed {
    return $this->value;
  }
}