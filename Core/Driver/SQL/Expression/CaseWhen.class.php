<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\Condition\Condition;
use Core\Driver\SQL\SQL;

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

  function getExpression(SQL $sql, array &$params): string {
    $condition = $sql->buildCondition($this->getCondition(), $params);

    // psql requires constant values here
    $trueCase = $sql->addValue($this->getTrueCase(), $params, true);
    $falseCase = $sql->addValue($this->getFalseCase(), $params, true);

    return "CASE WHEN $condition THEN $trueCase ELSE $falseCase END";
  }
}