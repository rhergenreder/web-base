<?php

namespace Core\Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class EnumArr extends Enum {

  public function __construct(array $values) {
    parent::__construct(...$values);
  }

}