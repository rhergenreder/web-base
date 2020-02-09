<?php

namespace Api\Parameter;

class Parameter {
  const TYPE_INT       = 0;
  const TYPE_FLOAT     = 1;
  const TYPE_BOOLEAN   = 2;
  const TYPE_STRING    = 3;
  const TYPE_DATE      = 4;
  const TYPE_TIME      = 5;
  const TYPE_DATE_TIME = 6;
  const TYPE_EMAIL     = 7;

  // only internal access
  const TYPE_RAW       = 8;
  const TYPE_ARRAY     = 9;

  const names = array('Integer', 'Float', 'Boolean', 'String', 'Date', 'Time', 'DateTime', 'E-Mail', 'Raw', 'Array');

  public $name;
  public $value;
  public $optional;
  public $type;
  public $typeName;

  public function __construct($name, $type, $optional = FALSE, $defaultValue = NULL) {
    $this->name = $name;
    $this->optional = $optional;
    $this->value = $defaultValue;
    $this->type = $type;
    $this->typeName = $this->getTypeName();
  }

  public function getTypeName() {
    return ($this->type >= 0 && $this->type < count(Parameter::names)) ? Parameter::names[$this->type] : "INVALID";
  }

  public function toString() {
    $typeName = Parameter::names[$this->type];

    $str = "$typeName $this->name";
    $defaultValue = (is_null($this->value) ? 'NULL' : $this->value);
    if($this->optional) {
      $str = "[$str = $defaultValue]";
    }

    return $str;
  }

  public static function parseType($value) {
    if(is_array($value))
      return Parameter::TYPE_ARRAY;
    else if(is_numeric($value) && intval($value) == $value)
      return Parameter::TYPE_INT;
    else if(is_float($value) || (is_numeric($value) && floatval($value) == $value))
      return Parameter::TYPE_FLOAT;
    else if(is_bool($value) || $value == "true" || $value == "false")
      return Parameter::TYPE_BOOLEAN;
    else if(is_a($value, 'DateTime'))
      return Parameter::TYPE_DATE_TIME;
    else if(($d = \DateTime::createFromFormat('Y-m-d', $value)) && $d->format('Y-m-d') === $value)
      return Parameter::TYPE_DATE;
    else if(($d = \DateTime::createFromFormat('H:i:s', $value)) && $d->format('H:i:s') === $value)
      return Parameter::TYPE_TIME;
    else if(($d = \DateTime::createFromFormat('Y-m-d H:i:s', $value)) && $d->format('Y-m-d H:i:s') === $value)
      return Parameter::TYPE_DATE_TIME;
    else if (filter_var($value, FILTER_VALIDATE_EMAIL))
      return Parameter::TYPE_EMAIL;
    else
      return Parameter::TYPE_STRING;
  }

  public function parseParam($value) {
    switch($this->type) {
      case Parameter::TYPE_INT:
        if(is_numeric($value) && intval($value) == $value) {
          $this->value = intval($value);
          return true;
        }
        return false;

      case Parameter::TYPE_FLOAT:
        if(is_numeric($value) && (floatval($value) == $value || intval($value) == $value)) {
          $this->value = floatval($value);
          return true;
        }
        return false;

      case Parameter::TYPE_BOOLEAN:
        if(strcasecmp($value, 'true') === 0)
          $this->value = true;
        else if(strcasecmp($value, 'false') === 0)
          $this->value = false;
        else if(is_bool($value))
          $this->value = (bool)$value;
        else
          return false;
        return true;

      case Parameter::TYPE_DATE:
        if(is_a($value, "DateTime")) {
          $this->value = $value;
          return true;
        }

        $d = DateTime::createFromFormat('Y-m-d', $value);
        if($d && $d->format('Y-m-d') === $value) {
          $this->value = $d;
          return true;
        }
        return false;

      case Parameter::TYPE_TIME:
        if(is_a($value, "DateTime")) {
          $this->value = $value;
          return true;
        }

        $d = DateTime::createFromFormat('H:i:s', $value);
        if($d && $d->format('H:i:s') === $value) {
          $this->value = $d;
          return true;
        }
        return false;

      case Parameter::TYPE_DATE_TIME:
        if(is_a($value, 'DateTime')) {
          $this->value = $value;
          return true;
        } else {
          $d = DateTime::createFromFormat('Y-m-d H:i:s', $value);
          if($d && $d->format('Y-m-d H:i:s') === $value) {
            $this->value = $d;
            return true;
          }
        }
        return false;

      case Parameter::TYPE_EMAIL:
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
          $this->value = $value;
          return true;
        }
        return false;

      case Parameter::TYPE_ARRAY:
        if(is_array($value)) {
          $this->value = $value;
          return true;
        }
        return false;

      default:
        $this->value = $value;
        return true;
    }
  }
}

?>
