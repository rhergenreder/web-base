<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;

class ApiKey extends DatabaseEntity {

  private bool $active;
  #[MaxLength(64)] public String $token;
  public \DateTime $validUntil;
  public User $user;

  public function getValidUntil(): \DateTime {
    return $this->validUntil;
  }

  public static function create(User $user, int $days = 30): ApiKey {
    $apiKey = new ApiKey();
    $apiKey->user = $user;
    $apiKey->token = generateRandomString(64);
    $apiKey->validUntil = (new \DateTime())->modify("+$days days");
    $apiKey->active = true;
    return $apiKey;
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