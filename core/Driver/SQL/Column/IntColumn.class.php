<?php

namespace Driver\SQL\Column;

class IntColumn extends Column {

  protected string $type;
  private bool $unsigned;

  public function __construct(string $name, bool $nullable = false, $defaultValue = NULL, bool $unsigned = false) {
    parent::__construct($name, $nullable, $defaultValue);
    $this->type = "INTEGER";
    $this->unsigned = $unsigned;
  }

  public function isUnsigned(): bool {
    return $this->unsigned;
  }

  public function getType(): string {
    return $this->type;
  }
}
