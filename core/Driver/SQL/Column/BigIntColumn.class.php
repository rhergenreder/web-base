<?php

namespace Driver\SQL\Column;

class BigIntColumn extends IntColumn {

  public function __construct(string $name, bool $nullable, $defaultValue, bool $unsigned) {
    parent::__construct($name, $nullable, $defaultValue, $unsigned);
    $this->type = "BIGINT";
  }
}