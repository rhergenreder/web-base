<?php

namespace Core\API\Parameter;

use DateTime;

class Parameter {
  const TYPE_INT = 0;
  const TYPE_FLOAT = 1;
  const TYPE_BOOLEAN = 2;
  const TYPE_STRING = 3;
  const TYPE_DATE = 4;
  const TYPE_TIME = 5;
  const TYPE_DATE_TIME = 6;
  const TYPE_EMAIL = 7;

  // only internal access
  const TYPE_RAW = 8;

  // only json will work here I guess
  // nope. also name[]=value
  const TYPE_ARRAY = 9;
  const TYPE_MIXED = 10;

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
  public ?array $choices;

  public function __construct(string $name, int $type, bool $optional = FALSE, $defaultValue = NULL, ?array $choices = NULL) {
    $this->name = $name;
    $this->optional = $optional;
    $this->defaultValue = $defaultValue;
    $this->value = $defaultValue;
    $this->type = $type;
    $this->choices = $choices;
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
    return match ($this->type) {
      self::TYPE_DATE => self::DATE_FORMAT,
      self::TYPE_TIME => self::TIME_FORMAT,
      self::TYPE_DATE_TIME => self::DATE_TIME_FORMAT,
      self::TYPE_EMAIL => "email",
      default => null,
    };
  }

  public function getTypeName(): string {
    $typeName = Parameter::names[$this->type] ?? "INVALID";
    if ($this->choices) {
      $typeName .= ", choices=" . json_encode($this->choices);
    }

    $format = $this->getSwaggerFormat();
    if ($format && $this->type !== self::TYPE_EMAIL) {
      $typeName .= ", format='$format'";
    }

    return $typeName;
  }

  public function toString(): string {
    $typeName = Parameter::names[$this->type];

    $str = "$typeName $this->name";
    $defaultValue = (is_null($this->value) ? 'NULL' : $this->value);
    if ($this->optional) {
      $str = "[$str = $defaultValue]";
    }

    return $str;
  }

  public static function parseType(mixed $value, bool $strict = false): int {
    if (is_array($value))
      return Parameter::TYPE_ARRAY;
    else if (is_int($value) || (!$strict && is_numeric($value) && intval($value) == $value))
      return Parameter::TYPE_INT;
    else if (is_float($value) || (!$strict && is_numeric($value) && floatval($value) == $value))
      return Parameter::TYPE_FLOAT;
    else if (is_bool($value) || (!$strict && ($value == "true" || $value == "false")))
      return Parameter::TYPE_BOOLEAN;
    else if (is_a($value, 'DateTime'))
      return Parameter::TYPE_DATE_TIME;
    else if ($value !== null && ($d = DateTime::createFromFormat(self::DATE_FORMAT, $value)) && $d->format(self::DATE_FORMAT) === $value)
      return Parameter::TYPE_DATE;
    else if ($value !== null && ($d = DateTime::createFromFormat(self::TIME_FORMAT, $value)) && $d->format(self::TIME_FORMAT) === $value)
      return Parameter::TYPE_TIME;
    else if ($value !== null && ($d = DateTime::createFromFormat(self::DATE_TIME_FORMAT, $value)) && $d->format(self::DATE_TIME_FORMAT) === $value)
      return Parameter::TYPE_DATE_TIME;
    else if (filter_var($value, FILTER_VALIDATE_EMAIL))
      return Parameter::TYPE_EMAIL;
    else
      return Parameter::TYPE_STRING;
  }

  public function parseParam($value): bool {

    $valid = false;
    switch ($this->type) {
      case Parameter::TYPE_INT:
        if (is_numeric($value) && intval($value) == $value) {
          $this->value = intval($value);
          $valid = true;
        }
        break;

      case Parameter::TYPE_FLOAT:
        if (is_numeric($value) && (floatval($value) == $value || intval($value) == $value)) {
          $this->value = floatval($value);
          $valid = true;
        }
        break;

      case Parameter::TYPE_BOOLEAN:
        if (strcasecmp($value, 'true') === 0) {
          $this->value = true;
          $valid = true;
        } else if (strcasecmp($value, 'false') === 0) {
          $this->value = false;
          $valid = true;
        } else if (is_bool($value)) {
          $this->value = (bool)$value;
          $valid = true;
        }
        break;

      case Parameter::TYPE_TIME:
      case Parameter::TYPE_DATE:
      case Parameter::TYPE_DATE_TIME:
        if ($value instanceof DateTime) {
          $this->value = $value;
          $valid = true;
        } else if (is_int($value) || (is_string($value) && preg_match("/^\d+$/", $value))) {
          $this->value = (new \DateTime())->setTimestamp(intval($value));
          $valid = true;
        } else {
          $format = $this->getFormat();
          $d = DateTime::createFromFormat($format, $value);
          if ($d && $d->format($format) === $value) {
            $this->value = $d;
            $valid = true;
          }
        }
        break;

      case Parameter::TYPE_EMAIL:
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
          $this->value = $value;
          $valid = true;
        }
        break;

      case Parameter::TYPE_ARRAY:
        if (is_array($value)) {
          $this->value = $value;
          $valid = true;
        }
        break;

      default:
        $this->value = $value;
        $valid = true;
        break;
    }

    if ($valid && $this->choices) {
      if (!in_array($this->value, $this->choices)) {
        return false;
      }
    }

    return $valid;
  }

  private function getFormat(): ?string {
    if ($this->type === self::TYPE_TIME) {
      return self::TIME_FORMAT;
    } else if ($this->type === self::TYPE_DATE) {
      return self::DATE_FORMAT;
    } else if ($this->type === self::TYPE_DATE_TIME) {
      return self::DATE_TIME_FORMAT;
    } else {
      return null;
    }
  }
}