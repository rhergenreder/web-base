<?php

namespace Core\Driver\SQL\Type;

use Core\Driver\SQL\Column\StringColumn;

class CurrentTable extends StringColumn {
  public function __construct() {
    parent::__construct("CURRENT_TABLE");
  }
}