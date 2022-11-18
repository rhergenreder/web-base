<?php

namespace Core\Driver\SQL\Column;

use Core\Driver\SQL\Column\Column;

class NumericColumn extends Column {

  protected string $type;
  private ?int $totalDigits;
  private ?int $decimalDigits;

  public function __construct(string $name, bool $nullable, $defaultValue = NULL, ?int $totalDigits = null, ?int $decimalDigits = null) {
    parent::__construct($name, $nullable, $defaultValue);
    $this->totalDigits = $totalDigits;
    $this->decimalDigits = $decimalDigits;
    $this->type = "NUMERIC";
  }

  public function getDecimalDigits(): ?int {
    return $this->decimalDigits;
  }

  public function getTotalDigits(): ?int {
    return $this->totalDigits;
  }

  public function getTypeName(): string {
    return $this->type;
  }
}