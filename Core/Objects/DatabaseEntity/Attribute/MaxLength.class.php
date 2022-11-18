<?php

namespace Core\Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)] class MaxLength {
  private int $maxLength;

  function __construct(int $maxLength) {
    $this->maxLength = $maxLength;
  }

  public function getValue(): int {
    return $this->maxLength;
  }
}