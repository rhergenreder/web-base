<?php

namespace Core\API\Parameter;

class RegexType extends StringType {

  public string $pattern;

  public function __construct(string $name, string $pattern, bool $optional = FALSE,
                              ?string $defaultValue = NULL) {
    $this->pattern = $pattern;

    if (!startsWith($this->pattern, "/") || !endsWith($this->pattern, "/")) {
      $this->pattern = "/" . $this->pattern . "/";
    }

    parent::__construct($name, -1, $optional, $defaultValue);
  }

  public function parseParam($value): bool {
    if (!parent::parseParam($value)) {
      return false;
    }

    $matches = [];
    if (!preg_match($this->pattern, $this->value, $matches)) {
      return false;
    }

    return strlen($matches[0]) === strlen($this->value);
  }

  public function getTypeName(): string {
    return parent::getTypeName() . " ($this->pattern)";
  }
}