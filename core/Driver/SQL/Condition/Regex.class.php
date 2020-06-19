<?php

namespace Driver\SQL\Condition;

class Regex extends Condition {

  private $leftExpression;
  private $rightExpression;

  public function __construct($leftExpression, $rightExpression) {
    $this->leftExpression = $leftExpression;
    $this->rightExpression = $rightExpression;
  }

  public function getLeftExp() { return $this->leftExpression; }
  public function getRightExp() { return $this->rightExpression; }
}