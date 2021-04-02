<?php

namespace Driver\SQL;

class Keyword {

  private string $value;

  public function __construct(string $value) {
    $this->value = $value;
  }

  public function getValue(): string { return $this->value; }

}