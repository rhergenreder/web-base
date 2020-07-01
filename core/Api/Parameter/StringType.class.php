<?php

namespace Api\Parameter;

class StringType extends Parameter {

  public int $maxLength;
  public function __construct($name, $maxLength = -1, $optional = FALSE, $defaultValue = NULL) {
    $this->maxLength = $maxLength;
    parent::__construct($name, Parameter::TYPE_STRING, $optional, $defaultValue);
  }

  public function parseParam($value) {
    if(!is_string($value)) {
      return false;
    }

    if($this->maxLength > 0 && strlen($value) > $this->maxLength) {
      return false;
    }

    $this->value = $value;
    return true;
  }

  public function getTypeName() {
    $maxLength = ($this->maxLength > 0 ? "($this->maxLength)" : "");
    return parent::getTypeName() . $maxLength;
  }

  public function toString() {
    $typeName = $this->getTypeName();
    $str = "$typeName $this->name";
    $defaultValue = (is_null($this->value) ? 'NULL' : $this->value);
    if($this->optional) {
      $str = "[$str = $defaultValue]";
    }

    return $str;
  }
}