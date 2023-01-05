<?php

namespace Core\Driver\SQL\Condition;

use Core\Driver\SQL\SQL;

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

  function getExpression(SQL $sql, array &$params): string {
    $keyword = $this->getKeyword();
    $left = $sql->addValue($this->getLeftExp(), $params);
    $right = $sql->addValue($this->getRightExp(), $params);
    return "$left $keyword $right";
  }
}