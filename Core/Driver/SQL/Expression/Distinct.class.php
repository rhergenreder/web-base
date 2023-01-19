<?php

namespace Core\Driver\SQL\Expression;

class Distinct extends AbstractFunction {

  public function __construct(mixed $value) {
    parent::__construct("DISTINCT", $value);
  }

}