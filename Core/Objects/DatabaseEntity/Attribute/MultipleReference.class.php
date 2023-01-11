<?php

namespace Core\Objects\DatabaseEntity\Attribute;

// Managed NM table, e.g. #[MultipleReference(Y::class, "x", "z")] in X::class will use
// the table of Y::class and lookup values by column "x_id" and create an array with keys of "z_id" holding a reference of Y

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MultipleReference {

  private string $className;
  private string $thisProperty;
  private string $relProperty;

  public function __construct(string $className, string $thisProperty, string $relProperty = "id") {
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