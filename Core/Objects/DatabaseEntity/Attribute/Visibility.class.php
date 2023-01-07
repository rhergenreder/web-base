<?php

namespace Core\Objects\DatabaseEntity\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)] class Visibility {

  // Visibility enum
  const NONE = 0;
  const BY_GROUP = 1;
  const ALL = 2;

  private int $visibility;
  private array $groups;

  public function __construct(int $visibility, int ...$groups) {
    $this->visibility = $visibility;
    $this->groups = $groups;
  }

  public function getType(): int {
    return $this->visibility;
  }

  public function getGroups(): array {
    return $this->groups;
  }
}