<?php

namespace Driver\SQL\Expression;

use Driver\SQL\Condition\Compare;

# TODO: change confusing class inheritance here
class Add extends Compare {

  public function __construct(string $col, $val) {
    parent::__construct($col, $val, "+");
  }

}