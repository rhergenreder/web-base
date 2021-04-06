<?php

namespace Driver\SQL\Column;

class SerialColumn extends Column {

  public function __construct(string $name, $defaultValue = NULL) {
    parent::__construct($name, false, $defaultValue); # not nullable
  }

}