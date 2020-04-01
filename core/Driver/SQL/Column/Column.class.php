<?php

namespace Driver\SQL\Column;

class Column {

  private $name;
  private $nullable;
  private $defaultValue;

  public function __construct($name, $nullable = false, $defaultValue = NULL) {
    $this->name = $name;
    $this->nullable = $nullable;
    $this->defaultValue = $defaultValue;
  }

  public function getName() { return $this->name; }
  public function notNull() { return !$this->nullable; }
  public function getDefaultValue() { return $this->defaultValue; }

}

?>
