<?php

namespace Core\API\Parameter;

class UuidType extends RegexType {

  const UUID_PATTERN = "[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}";

  public function __construct(string $name, bool $optional = FALSE, ?string $defaultValue = NULL) {
    parent::__construct($name, self::UUID_PATTERN, $optional, $defaultValue);
  }

}