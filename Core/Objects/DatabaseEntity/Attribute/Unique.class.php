<?php

namespace Core\Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY|\Attribute::TARGET_CLASS)] class Unique {

  private array $columns;

  public function __construct(string ...$columns) {
    $this->columns = $columns;
  }

  public function getColumns(): array {
    return $this->columns;
  }
}