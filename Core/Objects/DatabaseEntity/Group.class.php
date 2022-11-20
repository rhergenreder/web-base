<?php

namespace Core\Objects\DatabaseEntity;

use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;

class Group extends DatabaseEntity {

  const ADMIN = 1;
  const MODERATOR = 3;
  const SUPPORT = 2;

  const GROUPS = [
    self::ADMIN => "Administrator",
    self::MODERATOR => "Moderator",
    self::SUPPORT => "Support",
  ];

  #[MaxLength(32)] public string $name;
  #[MaxLength(10)] public string $color;

  public function __construct(?int $id, string $name, string $color) {
    parent::__construct($id);
    $this->name = $name;
    $this->color = $color;
  }

  public function jsonSerialize(): array {
    return [
      "id" => $this->getId(),
      "name" => $this->name,
      "color" => $this->color
    ];
  }
}