<?php

namespace Driver\SQL\Type;

use Driver\SQL\Column\StringColumn;

class CurrentTable extends StringColumn {
  public function __construct() {
    parent::__construct("CURRENT_TABLE");
  }
}