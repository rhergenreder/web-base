<?php

namespace Core\Driver\SQL\Column;

class BoolColumn extends Column {

  public function __construct(string $name, bool $defaultValue = false) {
    parent::__construct($name, false, $defaultValue);
  }

}
