<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\SQL;

class Alias extends Expression {

  private mixed $value;
  private string $alias;

  public function __construct(mixed $value, string $alias) {
    $this->value = $value;
    $this->alias = $alias;
  }

  public function getAlias(): string {
    return $this->alias;
  }

  public function getValue(): mixed {
    return $this->value;
  }

  protected function addValue(SQL $sql, array &$params): string {
    return $sql->addValue($this->value, $params);
  }

  public function getExpression(SQL $sql, array &$params): string {
    return $this->addValue($sql, $params) . " AS " . $this->getAlias();
  }
}