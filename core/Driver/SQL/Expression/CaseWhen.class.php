<?php

namespace Driver\SQL\Expression;

use Driver\SQL\Condition\Condition;

class CaseWhen extends Expression {

  private Condition $condition;
  private $trueCase;
  private $falseCase;

  public function __construct(Condition $condition, $trueCase, $falseCase) {
    $this->condition = $condition;
    $this->trueCase = $trueCase;
    $this->falseCase = $falseCase;
  }

  public function getCondition(): Condition { return $this->condition; }
  public function getTrueCase() { return $this->trueCase; }
  public function getFalseCase() { return $this->falseCase; }

}