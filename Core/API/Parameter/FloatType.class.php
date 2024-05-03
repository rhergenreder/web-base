<?php

namespace Core\API\Parameter;

class FloatType extends Parameter {

  public float $minValue;
  public float $maxValue;
  public function __construct(string $name, float $minValue = PHP_FLOAT_MIN, float $maxValue = PHP_FLOAT_MAX,
                              bool $optional = FALSE, ?float $defaultValue = NULL, ?array $choices = NULL) {
    $this->minValue = $minValue;
    $this->maxValue = $maxValue;
    parent::__construct($name, Parameter::TYPE_FLOAT, $optional, $defaultValue, $choices);
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
    $hasMin = $this->minValue > PHP_FLOAT_MIN;
    $hasMax = $this->maxValue < PHP_FLOAT_MAX;

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