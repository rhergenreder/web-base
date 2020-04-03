<?php

namespace Driver\SQL\Constraint;

class PrimaryKey extends Constraint {

  public function __construct(...$names) {
    parent::__construct((!empty($names) && is_array($names[0])) ? $names[0] : $names);
  }

}
