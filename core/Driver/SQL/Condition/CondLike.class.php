<?php

namespace Driver\SQL\Condition;

class CondLike extends CondKeyword {

  public function __construct($leftExpression, $rightExpression) {
    parent::__construct("LIKE", $leftExpression, $rightExpression);
  }
}