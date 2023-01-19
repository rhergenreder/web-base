<?php

namespace Core\Driver\SQL\Expression;

class Upper extends AbstractFunction {

  public function __construct(mixed $value) {
    parent::__construct("UPPER", $value);
  }

}