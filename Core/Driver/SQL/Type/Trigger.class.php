<?php

namespace Core\Driver\SQL\Type;

use Core\Driver\SQL\Keyword;

class Trigger extends Keyword {
  public function __construct() {
    parent::__construct("TRIGGER");
  }
}