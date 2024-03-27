<?php

namespace Core\API\Parameter;

class ArrayType extends Parameter {

  private Parameter $elementParameter;
  public int $elementType;
  public int $canBeOne;

  /**
   * ArrayType constructor.
   * @param string $name the name of the parameter
   * @param int $elementType element type inside the array, for example, allow only integer values (Parameter::TYPE_INT)
   * @param bool $canBeOne true, if a single element can be passed inside the request (e.g. array=1 instead of array[]=1). Will be automatically casted to an array
   * @param bool $optional true if the parameter is optional
   * @param array|null $defaultValue the default value to use, if the parameter is not given
   */
  public function __construct(string $name, int $elementType = Parameter::TYPE_MIXED, bool $canBeOne = false, bool $optional = FALSE, ?array $defaultValue = NULL) {
    $this->elementType = $elementType;
    $this->elementParameter = new Parameter('', $elementType);
    $this->canBeOne = $canBeOne;
    parent::__construct($name, Parameter::TYPE_ARRAY, $optional, $defaultValue);
  }

  public function parseParam($value): bool {
    if (!is_array($value)) {
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

  public function getTypeName(): string {
    $elementType = $this->elementParameter->getTypeName();
    return parent::getTypeName() . "($elementType)";
  }

  public function toString(): string {
    $typeName = $this->getTypeName();
    $str = "$typeName $this->name";
    $defaultValue = (is_null($this->value) ? 'NULL' : (is_array($this->value) ? '[' . implode(",", $this->value) . ']' : $this->value));
    if($this->optional) {
      $str = "[$str = $defaultValue]";
    }

    return $str;
  }
}