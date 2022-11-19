<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\EnumArr;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;

class UserToken extends DatabaseEntity {

  const TYPE_PASSWORD_RESET = "password_reset";
  const TYPE_EMAIL_CONFIRM = "email_confirm";
  const TYPE_INVITE = "invite";
  const TYPE_GPG_CONFIRM = "gpg_confirm";

  const TOKEN_TYPES = [
    self::TYPE_PASSWORD_RESET, self::TYPE_EMAIL_CONFIRM,
    self::TYPE_INVITE, self::TYPE_GPG_CONFIRM
  ];

  #[MaxLength(36)]
  private string $token;

  #[EnumArr(self::TOKEN_TYPES)]
  private string $tokenType;

  private User $user;
  private \DateTime $validUntil;

  #[DefaultValue(false)]
  private bool $used;

  public function __construct(User $user, string $token, string $type, int $validHours) {
    parent::__construct();
    $this->user = $user;
    $this->token = $token;
    $this->tokenType = $type;
    $this->validUntil = (new \DateTime())->modify("+$validHours HOUR");
    $this->used = false;
  }

  public function jsonSerialize(): array {
    return [
      "id" => $this->getId(),
      "token" => $this->token,
      "tokenType" => $this->tokenType
    ];
  }

  public function getType(): string {
    return $this->tokenType;
  }

  public function invalidate(SQL $sql): bool {
    $this->used = true;
    return $this->save($sql);
  }

  public function getUser(): User {
    return $this->user;
  }

  public function updateDurability(SQL $sql, int $validHours): bool {
    $this->validUntil = (new \DateTime())->modify("+$validHours HOURS");
    return $this->save($sql);
  }

  public function getToken(): string {
    return $this->token;
  }
}