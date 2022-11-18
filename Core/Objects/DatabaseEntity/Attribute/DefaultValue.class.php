<?php

namespace Core\Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)] class DefaultValue {

  private mixed $value;

  public function __construct(mixed $value) {
    $this->value = $value;
  }

  public function getValue() {
    if (is_string($this->value) && isClass($this->value)) {
      return new $this->value();
    }

    return $this->value;
  }

}