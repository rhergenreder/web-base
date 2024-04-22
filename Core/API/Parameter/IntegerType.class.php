<?php

namespace Core\API\Parameter;

class IntegerType extends Parameter {

  public int $minValue;
  public int $maxValue;
  public function __construct(string $name, int $minValue = PHP_INT_MIN, int $maxValue = PHP_INT_MAX,
                              bool $optional = FALSE, ?int $defaultValue = NULL, ?array $choices = NULL) {
    $this->minValue = $minValue;
    $this->maxValue = $maxValue;
    parent::__construct($name, Parameter::TYPE_INT, $optional, $defaultValue, $choices);
  }

  public function parseParam($value): bool {
    if (!parent::parseParam($value)) {
      return false;
    }

    $this->value = $value;
    if ($this->value < $this->minValue || $this->value > $this->maxValue) {
      return false;
    }

    return true;
  }

  public function getTypeName(): string {
    $typeName = parent::getTypeName();
    $hasMin = $this->minValue > PHP_INT_MIN;
    $hasMax = $this->maxValue < PHP_INT_MAX;

    if ($hasMin || $hasMax) {
      if ($hasMin && $hasMax) {
        $typeName .= " ($this->minValue - $this->maxValue)";
      } else if ($hasMin) {
        $typeName .= " (> $this->minValue)";
      } else if ($hasMax) {
        $typeName .= " (< $this->maxValue)";
      }
    }

    return $typeName;
  }

  public function toString(): string {
    $typeName = $this->getTypeName();
    $str = "$typeName $this->name";
    $defaultValue = (is_null($this->value) ? 'NULL' : $this->value);
    if ($this->optional) {
      $str = "[$str = $defaultValue]";
    }

    return $str;
  }
}