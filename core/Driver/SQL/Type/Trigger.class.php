<?php

namespace Driver\SQL\Type;

use Driver\SQL\Keyword;

class Trigger extends Keyword {
  public function __construct() {
    parent::__construct("TRIGGER");
  }
}