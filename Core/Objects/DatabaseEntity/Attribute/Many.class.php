<?php

namespace Core\Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)] class Many {

  private string $type;

  public function __construct(string $type) {
    $this->type = $type;
  }

  public function getValue(): string {
    return $this->type;
  }
}