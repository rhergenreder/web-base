<?php

namespace Driver\SQL\Column;

class IntColumn extends Column {

  public function __construct(string $name, bool $nullable = false, $defaultValue = NULL) {
    parent::__construct($name, $nullable, $defaultValue);
  }

}
