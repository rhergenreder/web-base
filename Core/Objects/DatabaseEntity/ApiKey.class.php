<?php

namespace Core\Objects\DatabaseEntity;

use Core\Objects\DatabaseEntity\Attribute\MaxLength;

class ApiKey extends DatabaseEntity {

  private bool $active;
  #[MaxLength(64)] public String $apiKey;
  public \DateTime $validUntil;
  public User $user;

  public function __construct(?int $id = null) {
    parent::__construct($id);
    $this->active = true;
  }

  public function jsonSerialize(): array {
    return [
      "id" => $this->getId(),
      "active" => $this->active,
      "apiKey" => $this->apiKey,
      "validUntil" => $this->validUntil->getTimestamp()
    ];
  }
}