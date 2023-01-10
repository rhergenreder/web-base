<?php

namespace Core\Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Multiple {

  private string $className;

  public function __construct(string $className) {
    $this->className = $className;
  }

  public function getClassName(): string {
    return $this->className;
  }
}