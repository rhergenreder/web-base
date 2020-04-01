<?php

namespace Driver\SQL;

class Keyword {

  private $value;

  public function __construct($value) {
    $this->value = $value;
  }

  public function getValue() { return $this->value; }

}

?>
