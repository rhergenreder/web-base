<?php

namespace Core\Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ExtendingEnum extends EnumArr {

  private array $mappings;

  public function __construct(array $values) {
    parent::__construct(array_keys($values));
    $this->mappings = $values;
  }

  public function getMappings(): array {
    return $this->mappings;
  }
}