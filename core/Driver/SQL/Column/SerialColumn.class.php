<?php

namespace Driver\SQL\Column;

class SerialColumn extends Column {

  public function __construct($name, $defaultValue=NULL) {
    parent::__construct($name, false, $defaultValue); # not nullable
  }

}