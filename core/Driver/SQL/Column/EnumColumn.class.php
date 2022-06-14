<?php

namespace Driver\SQL\Column;

class EnumColumn extends Column {

  private array $values;

  public function __construct(string $name, array $values, bool $nullable = false, $defaultValue = NULL) {
    parent::__construct($name, $nullable, $defaultValue);
    $this->values = $values;
  }

  public function addValue(string $value) {
    $this->values[] = $value;
  }

  public function getValues(): array { return $this->values; }
}
