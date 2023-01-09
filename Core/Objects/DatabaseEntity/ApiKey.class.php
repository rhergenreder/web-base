<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;

class ApiKey extends DatabaseEntity {

  private bool $active;
  #[MaxLength(64)] public String $apiKey;
  public \DateTime $validUntil;
  public User $user;

  public function __construct(?int $id = null) {
    parent::__construct($id);
    $this->active = true;
  }

  public function getValidUntil(): \DateTime {
    return $this->validUntil;
  }

  public function refresh(SQL $sql, int $days): bool {
    $this->validUntil = (new \DateTime())->modify("+$days days");
    return $this->save($sql, ["validUntil"]);
  }

  public function revoke(SQL $sql): bool {
    $this->active = false;
    return $this->save($sql, ["active"]);
  }
}