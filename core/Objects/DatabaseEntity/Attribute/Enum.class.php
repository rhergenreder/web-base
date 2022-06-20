<?php

namespace Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)] class Enum {

  private array $values;

  public function __construct(string ...$values) {
    $this->values = $values;
  }

  public function getValues(): array {
    return $this->values;
  }

}