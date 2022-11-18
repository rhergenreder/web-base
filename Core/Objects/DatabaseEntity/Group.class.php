<?php

namespace Core\Objects\DatabaseEntity;

use Core\Objects\DatabaseEntity\Attribute\MaxLength;

class Group extends DatabaseEntity {

  #[MaxLength(32)] public string $name;
  #[MaxLength(10)] public string $color;

  public function __construct(?int $id = null) {
    parent::__construct($id);
  }

  public function jsonSerialize(): array {
    return [
      "id" => $this->getId(),
      "name" => $this->name,
      "color" => $this->color
    ];
  }
}