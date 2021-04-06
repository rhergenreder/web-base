<?php

namespace Driver\SQL\Column;

class StringColumn extends Column {

  private ?int $maxSize;

  public function __construct(string $name, ?int $maxSize = null, bool $nullable = false, $defaultValue = null) {
    parent::__construct($name, $nullable, $defaultValue);
    $this->maxSize = $maxSize;
  }

  public function getMaxSize(): ?int { return $this->maxSize; }
}