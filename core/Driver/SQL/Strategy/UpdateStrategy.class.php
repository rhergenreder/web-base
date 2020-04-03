<?php

namespace Driver\SQL\Strategy;

class UpdateStrategy extends Strategy {

  private array $values;

  public function __construct($values) {
    $this->values = $values;
  }

  public function getValues() { return $this->values; }
}