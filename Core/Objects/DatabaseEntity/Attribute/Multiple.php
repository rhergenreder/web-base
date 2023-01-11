<?php

namespace Core\Objects\DatabaseEntity\Attribute;

// Unmanaged NM table, e.g. #[Multiple(Group::class)] for property 'groups' in User::class will create a
// table called NM_User_groups with just two columns (user_id, group_id)

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