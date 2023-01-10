<?php

namespace Core\Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)] class BigInt {

  private bool $unsigned;

  public function __construct(bool $unsigned = false) {
    $this->unsigned = $unsigned;
  }

  public function isUnsigned(): bool {
    return $this->unsigned;
  }
}