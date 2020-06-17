<?php

namespace Driver\SQL\Expression;

use Driver\SQL\Condition\Compare;

class Add extends Compare {

  public function __construct($col, $val) {
    parent::__construct($col, $val, "+");
  }

}