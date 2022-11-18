<?php

namespace Core\Driver\SQL\Condition;

class CondIn extends Condition {

  private $needle;
  private $haystack;

  public function __construct($needle, $haystack) {
    $this->needle = $needle;
    $this->haystack = $haystack;
  }

  public function getNeedle() { return $this->needle; }
  public function getHaystack() { return $this->haystack; }
}