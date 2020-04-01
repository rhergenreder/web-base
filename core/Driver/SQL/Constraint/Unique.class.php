<?php

namespace Driver\SQL\Constraint;

class Unique extends Constraint {

  public function __construct(...$names) {
    parent::__construct((!empty($names) && is_array($names[0])) ? $names[0] : $names);
  }

};

?>
