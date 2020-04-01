<?php

namespace Driver\SQL\Column;

class JsonColumn extends Column {

  public function __construct($name, $nullable=false, $defaultValue=null) {
    parent::__construct($name, $nullable, $defaultValue);
  }

}

?>
