<?php

namespace Core\Driver\SQL\Column;

class DateTimeColumn extends Column {

  public function __construct(string $name, bool $nullable = false, $defaultValue = NULL) {
    parent::__construct($name, $nullable, $defaultValue);
  }
}