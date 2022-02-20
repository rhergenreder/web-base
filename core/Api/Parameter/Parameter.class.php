<?php

namespace Api\Parameter;

use DateTime;

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

  // only json will work here I guess
  // nope. also name[]=value
  const TYPE_ARRAY     = 9;
  const TYPE_MIXED     = 10;

  const names = array('Integer', 'Float', 'Boolean', 'String', 'Date', 'Time', 'DateTime', 'E-Mail', 'Raw', 'Array', 'Mixed');

  const DATE_FORMAT = "Y-m-d";
  const TIME_FORMAT = "H:i:s";
  const DATE_TIME_FORMAT = self::DATE_FORMAT . " " . self::TIME_FORMAT;

  private $defaultValue;

  public string $name;
  public $value;
  public bool $optional;
  public int $type;
  public string $typeName;

  public function __construct(string $name, int $type, bool $optional = FALSE, $defaultValue = NULL) {
    $this->name = $name;
    $this->optional = $optional;
    $this->defaultValue = $defaultValue;
    $this->value = $defaultValue;
    $this->type = $type;
    $this->typeName = $this->getTypeName();
  }

  public function reset() {
    $this->value = $this->defaultValue;
  }

  public function getSwaggerTypeName(): string {
    $typeName = strtolower(($this->type >= 0 && $this->type < count(Parameter::names)) ? Parameter::names[$this->type] : "invalid");
    if ($typeName === "mixed" || $typeName === "raw") {
      return "object";
    }

    if (!in_array($typeName, ["array", "boolean", "integer", "number", "object", "string"])) {
      return "string";
    }

    return $typeName;
  }

  public function getSwaggerFormat(): ?string {
    switch ($this->type) {
      case self::TYPE_DATE:
        return self::DATE_FORMAT;
      case self::TYPE_TIME:
        return self::TIME_FORMAT;
      case self::TYPE_DATE_TIME:
        return self::DATE_TIME_FORMAT;
      case self::TYPE_EMAIL:
        return "email";
      default:
        return null;
    }
  }

  public function getTypeName(): string {
    return ($this->type >= 0 && $this->type < count(Parameter::names)) ? Parameter::names[$this->type] : "INVALID";
  }

  public function toString(): string {
    $typeName = Parameter::names[$this->type];

    $str = "$typeName $this->name";
    $defaultValue = (is_null($this->value) ? 'NULL' : $this->value);
    if($this->optional) {
      $str = "[$str = $defaultValue]";
    }

    return $str;
  }

  public static function parseType($value): int {
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
    else if(($d = DateTime::createFromFormat(self::DATE_FORMAT, $value)) && $d->format(self::DATE_FORMAT) === $value)
      return Parameter::TYPE_DATE;
    else if(($d = DateTime::createFromFormat(self::TIME_FORMAT, $value)) && $d->format(self::TIME_FORMAT) === $value)
      return Parameter::TYPE_TIME;
    else if(($d = DateTime::createFromFormat(self::DATE_TIME_FORMAT, $value)) && $d->format(self::DATE_TIME_FORMAT) === $value)
      return Parameter::TYPE_DATE_TIME;
    else if (filter_var($value, FILTER_VALIDATE_EMAIL))
      return Parameter::TYPE_EMAIL;
    else
      return Parameter::TYPE_STRING;
  }

  public function parseParam($value): bool {
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

        $d = DateTime::createFromFormat(self::DATE_FORMAT, $value);
        if($d && $d->format(self::DATE_FORMAT) === $value) {
          $this->value = $d;
          return true;
        }
        return false;

      case Parameter::TYPE_TIME:
        if(is_a($value, "DateTime")) {
          $this->value = $value;
          return true;
        }

        $d = DateTime::createFromFormat(self::TIME_FORMAT, $value);
        if($d && $d->format(self::TIME_FORMAT) === $value) {
          $this->value = $d;
          return true;
        }
        return false;

      case Parameter::TYPE_DATE_TIME:
        if(is_a($value, 'DateTime')) {
          $this->value = $value;
          return true;
        } else {
          $d = DateTime::createFromFormat(self::DATE_TIME_FORMAT, $value);
          if($d && $d->format(self::DATE_TIME_FORMAT) === $value) {
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