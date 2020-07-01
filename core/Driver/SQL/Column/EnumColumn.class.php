<?php

namespace Driver\SQL\Column;

class EnumColumn extends Column {

  private array $values;

  public function __construct($name, $values, $nullable=false, $defaultValue=NULL) {
    parent::__construct($name, $nullable, $defaultValue);
    $this->values = $values;
  }

  public function getValues() { return $this->values; }
}
