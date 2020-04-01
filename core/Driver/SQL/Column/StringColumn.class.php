<?php

namespace Driver\SQL\Column;

class StringColumn extends Column {

  private $maxSize;

  public function __construct($name, $maxSize=null, $nullable=false, $defaultValue=null) {
    parent::__construct($name, $nullable, $defaultValue);
    $this->maxSize = $maxSize;
  }

  public function getMaxSize() { return $this->maxSize; }
}

?>
