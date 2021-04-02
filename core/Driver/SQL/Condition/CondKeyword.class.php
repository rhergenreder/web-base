<?php

namespace Driver\SQL\Condition;

abstract class CondKeyword extends Condition {

  private $leftExpression;
  private $rightExpression;
  private string $keyword;

  public function __construct(string $keyword, $leftExpression, $rightExpression) {
    $this->leftExpression = $leftExpression;
    $this->rightExpression = $rightExpression;
    $this->keyword = $keyword;
  }

  public function getLeftExp() { return $this->leftExpression; }
  public function getRightExp() { return $this->rightExpression; }
  public function getKeyword(): string { return $this->keyword; }
}