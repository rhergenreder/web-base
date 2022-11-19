<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\Condition\Compare;
use Core\Driver\SQL\Expression\CurrentTimeStamp;
use Core\Driver\SQL\Join;
use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Attribute\Transient;
use Core\Objects\DatabaseEntity\Attribute\Unique;

class User extends DatabaseEntity {

  #[MaxLength(32)] #[Unique] public string $name;
  #[MaxLength(128)] public string $password;
  #[MaxLength(64)] public string $fullName;
  #[MaxLength(64)] #[Unique] public ?string $email;
  #[MaxLength(64)] public ?string $profilePicture;
  private ?\DateTime $lastOnline;
  #[DefaultValue(CurrentTimeStamp::class)] public \DateTime $registeredAt;
  public bool $confirmed;
  #[DefaultValue(1)] public Language $language;
  public ?GpgKey $gpgKey;
  private ?TwoFactorToken $twoFactorToken;

  #[Transient] private array $groups;

  public function __construct(?int $id = null) {
    parent::__construct($id);
    $this->groups = [];
  }

  public function postFetch(SQL $sql, array $row) {
    parent::postFetch($sql, $row);
    $this->groups = [];

    $groups = Group::findAllBuilder($sql)
      ->addJoin(new Join("INNER", "UserGroup", "UserGroup.group_id", "Group.id"))
      ->where(new Compare("UserGroup.user_id", $this->id))
      ->execute();

    if ($groups) {
      $this->groups = $groups;
    }
  }

  public function getUsername(): string {
    return $this->name;
  }

  public function getFullName(): string {
    return $this->fullName;
  }

  public function getEmail(): ?string {
    return $this->email;
  }

  public function getGroups(): array {
    return $this->groups;
  }

  public function hasGroup(int $group): bool {
    return isset($this->groups[$group]);
  }

  public function getGPG(): ?GpgKey {
    return $this->gpgKey;
  }

  public function getTwoFactorToken(): ?TwoFactorToken {
    return $this->twoFactorToken;
  }

  public function getProfilePicture(): ?string {
    return $this->profilePicture;
  }

  public function __debugInfo(): array {
    return [
      'id' => $this->getId(),
      'username' => $this->name,
      'language' => $this->language->getName(),
    ];
  }

  public function jsonSerialize(): array {
    return [
      'id' => $this->getId(),
      'name' => $this->name,
      'fullName' => $this->fullName,
      'profilePicture' => $this->profilePicture,
      'email' => $this->email,
      'groups' => $this->groups ?? null,
      'language' => (isset($this->language) ? $this->language->jsonSerialize() : null),
      'session' => (isset($this->session) ? $this->session->jsonSerialize() : null),
      "gpg" => (isset($this->gpgKey) ? $this->gpgKey->jsonSerialize() : null),
      "2fa" => (isset($this->twoFactorToken) ? $this->twoFactorToken->jsonSerialize() : null),
      "reqisteredAt" => $this->registeredAt->getTimestamp(),
      "lastOnline" => $this->lastOnline->getTimestamp(),
      "confirmed" => $this->confirmed
    ];
  }

  public function update(SQL $sql): bool {
    $this->lastOnline = new \DateTime();
    return $this->save($sql, ["last_online", "language_id"]);
  }
}