<?php

namespace Driver\SQL\Column;

class BoolColumn extends Column {

  public function __construct($name, $defaultValue=false) {
    parent::__construct($name, false, $defaultValue);
  }

}
