<?php

namespace Core\API\Parameter;

class StringType extends Parameter {

  const UNLIMITED = -1;

  public int $maxLength;
  public function __construct(string $name, int $maxLength = self::UNLIMITED, bool $optional = FALSE,
                              ?string $defaultValue = NULL, ?array $choices = NULL) {
    $this->maxLength = $maxLength;
    parent::__construct($name, Parameter::TYPE_STRING, $optional, $defaultValue, $choices);
  }

  public function parseParam($value): bool {
    if (!parent::parseParam($value)) {
      return false;
    }

    // as long as it's numeric or bool, we can safely cast it to a string
    if (!is_string($value)) {
      if (is_bool($value) || is_int($value) || is_float($value)) {
        $this->value = strval($value);
      } else {
        return false;
      }
    }

    if ($this->maxLength > 0 && strlen($value) > $this->maxLength) {
      return false;
    }

    $this->value = $value;
    return true;
  }

  public function getTypeName(): string {
    $maxLength = ($this->maxLength > 0 ? "($this->maxLength)" : "");
    return parent::getTypeName() . $maxLength;
  }

  public function toString(): string {
    $typeName = $this->getTypeName();
    $str = "$typeName $this->name";
    $defaultValue = (is_null($this->value) ? 'NULL' : $this->value);
    if($this->optional) {
      $str = "[$str = $defaultValue]";
    }

    return $str;
  }
}