<?php

namespace Core\Driver\SQL\Condition;

use Core\Driver\SQL\Query\Select;
use Core\Driver\SQL\SQL;

class CondIn extends Condition {

  private mixed $needle;
  private mixed $haystack;

  public function __construct($needle, $haystack) {
    $this->needle = $needle;
    $this->haystack = $haystack;
  }

  public function getNeedle() { return $this->needle; }
  public function getHaystack() { return $this->haystack; }

  function getExpression(SQL $sql, array &$params): string {

    $needle = $sql->addValue($this->needle, $params);

    if (is_array($this->haystack)) {
      if (!empty($this->haystack)) {
        $values = array();
        foreach ($this->haystack as $value) {
          $values[] = $sql->addValue($value, $params);
        }

        $values = implode(",", $values);
        $values = "($values)";
      } else {
        $sql->getLogger()->error("Empty haystack for in-expression with needle: " . $needle);
        return false;
      }
    } else if ($this->haystack instanceof Select) {
      $values = $this->haystack->getExpression($sql, $params);
    } else {
      $sql->getLogger()->error("Unsupported in-expression value: " . get_class($this->haystack));
      return false;
    }

    return "$needle IN $values";
  }
}