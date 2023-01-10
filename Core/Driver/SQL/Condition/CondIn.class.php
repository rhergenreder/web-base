<?php

namespace Core\Driver\SQL\Condition;

use Core\Driver\SQL\Query\Select;
use Core\Driver\SQL\SQL;

class CondIn extends Condition {

  private $needle;
  private $haystack;

  public function __construct($needle, $haystack) {
    $this->needle = $needle;
    $this->haystack = $haystack;
  }

  public function getNeedle() { return $this->needle; }
  public function getHaystack() { return $this->haystack; }

  function getExpression(SQL $sql, array &$params): string {

    $haystack = $this->getHaystack();
    if (is_array($haystack)) {
      $values = array();
      foreach ($haystack as $value) {
        $values[] = $sql->addValue($value, $params);
      }

      $values = implode(",", $values);
      $values = "($values)";
    } else if($haystack instanceof Select) {
      $values = $haystack->getExpression($sql, $params);
    } else {
      $sql->getLogger()->error("Unsupported in-expression value: " . get_class($haystack));
      return false;
    }

    return $sql->addValue($this->needle, $params) . " IN $values";
  }
}