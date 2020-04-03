<?php

namespace Driver\SQL;

class Keyword {

  private string $value;

  public function __construct($value) {
    $this->value = $value;
  }

  public function getValue() { return $this->value; }

}