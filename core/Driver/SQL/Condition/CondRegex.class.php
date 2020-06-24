<?php

namespace Driver\SQL\Condition;

class CondRegex extends CondKeyword {

  public function __construct($leftExpression, $rightExpression) {
    parent::__construct("REGEXP", $leftExpression, $rightExpression);
  }

}