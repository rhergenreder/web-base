<?php

namespace Core\Driver\SQL\Column;

use Core\Driver\SQL\Expression\Expression;
use Core\Driver\SQL\SQL;

class Column extends Expression {

  private string $name;
  private bool $nullable;
  private $defaultValue;

  public function __construct(string $name, bool $nullable = false, $defaultValue = NULL) {
    $this->name = $name;
    $this->nullable = $nullable;
    $this->defaultValue = $defaultValue;
  }

  public function getName(): string { return $this->name; }
  public function notNull(): bool { return !$this->nullable; }
  public function getDefaultValue() { return $this->defaultValue; }

  function getExpression(SQL $sql, array &$params): string {
    return $sql->columnName($this->name);
  }
}