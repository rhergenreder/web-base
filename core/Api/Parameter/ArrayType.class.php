<?php

namespace Api\Parameter;

class ArrayType extends Parameter {

  private Parameter $elementParameter;
  public int $elementType;
  public int $canBeOne;

  public function __construct($name, $elementType = Parameter::TYPE_MIXED, $canBeOne=false, $optional = FALSE, $defaultValue = NULL) {
    $this->elementType = $elementType;
    $this->elementParameter = new Parameter('', $elementType);
    $this->canBeOne = $canBeOne;
    parent::__construct($name, Parameter::TYPE_ARRAY, $optional, $defaultValue);
  }

  public function parseParam($value) {
    if(!is_array($value)) {
      if (!$this->canBeOne) {
        return false;
      } else {
        $value = array($value);
      }
    }

    if ($this->elementType != Parameter::TYPE_MIXED) {
      foreach ($value as &$element) {
        if ($this->elementParameter->parseParam($element)) {
          $element = $this->elementParameter->value;
        } else {
          return false;
        }
      }
    }

    $this->value = $value;
    return true;
  }

  public function getTypeName() {
    $elementType = $this->elementParameter->getTypeName();
    return parent::getTypeName() . "($elementType)";
  }

  public function toString() {
    $typeName = $this->getTypeName();
    $str = "$typeName $this->name";
    $defaultValue = (is_null($this->value) ? 'NULL' : (is_array($this->value) ? '[' . implode(",", $this->value) . ']' : $this->value));
    if($this->optional) {
      $str = "[$str = $defaultValue]";
    }

    return $str;
  }
}