<?php

namespace Core\Driver\SQL\Expression;

class Sum extends AbstractFunction {

  public function __construct(mixed $value) {
    parent::__construct("SUM", $value);
  }

}