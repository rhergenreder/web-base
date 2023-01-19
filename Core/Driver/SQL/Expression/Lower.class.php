<?php

namespace Core\Driver\SQL\Expression;

class Lower extends AbstractFunction {

  public function __construct(mixed $value) {
    parent::__construct("LOWER", $value);
  }

}