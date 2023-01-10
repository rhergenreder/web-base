<?php

namespace Core\Objects\DatabaseEntity\Attribute;


namespace Core\Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MultipleReference {

  private string $className;
  private string $thisProperty;
  private string $relProperty;

  public function __construct(string $className, string $thisProperty, string $relProperty) {
    $this->className = $className;
    $this->thisProperty = $thisProperty;
    $this->relProperty = $relProperty;
  }

  public function getClassName(): string {
    return $this->className;
  }

  public function getThisProperty(): string {
    return $this->thisProperty;
  }

  public function getRelProperty(): string {
    return $this->relProperty;
  }
}