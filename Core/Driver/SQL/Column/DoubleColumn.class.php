<?php

namespace Core\Driver\SQL\Column;

class DoubleColumn extends NumericColumn {
  public function __construct(string $name, bool $nullable, $defaultValue = NULL, ?int $totalDigits = null, ?int $decimalDigits = null) {
    parent::__construct($name, $nullable, $defaultValue, $totalDigits, $decimalDigits);
    $this->type = "DOUBLE";
  }
}