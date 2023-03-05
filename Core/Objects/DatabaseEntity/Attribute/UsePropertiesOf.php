<?php

namespace Core\Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)] class UsePropertiesOf {

  private string $class;

  public function __construct(string $class) {
    $this->class = $class;
  }

  public function getClass(): string {
    return $this->class;
  }
}