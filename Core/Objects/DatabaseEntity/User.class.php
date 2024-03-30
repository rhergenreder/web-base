<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Expression\Alias;
use Core\Driver\SQL\Expression\Coalesce;
use Core\Driver\SQL\Expression\CurrentTimeStamp;
use Core\Driver\SQL\Expression\NullIf;
use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Attribute\Multiple;
use Core\Objects\DatabaseEntity\Attribute\Unique;
use Core\Objects\DatabaseEntity\Attribute\Visibility;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntityHandler;

class User extends DatabaseEntity {

  #[MaxLength(32)] #[Unique] public string $name;

  #[MaxLength(128)]
  #[Visibility(Visibility::NONE)]
  public string $password;

  #[MaxLength(64)]
  public string $fullName;

  #[MaxLength(64)]
  #[Unique]
  public ?string $email;

  #[MaxLength(64)]
  public ?string $profilePicture;

  #[Visibility(Visibility::BY_GROUP, Group::ADMIN, Group::SUPPORT)]
  private ?\DateTime $lastOnline;

  #[Visibility(Visibility::BY_GROUP, Group::ADMIN, Group::SUPPORT)]
  #[DefaultValue(CurrentTimeStamp::class)]
  public \DateTime $registeredAt;

  #[Visibility(Visibility::BY_GROUP, Group::ADMIN, Group::SUPPORT)]
  #[DefaultValue(false)]
  public bool $confirmed;

  #[Visibility(Visibility::BY_GROUP, Group::ADMIN, Group::SUPPORT)]
  #[DefaultValue(true)]
  public bool $active;

  #[DefaultValue(Language::AMERICAN_ENGLISH)] public Language $language;

  #[Visibility(Visibility::BY_GROUP, Group::ADMIN, Group::SUPPORT)]
  public ?GpgKey $gpgKey;

  #[Visibility(Visibility::BY_GROUP, Group::ADMIN, Group::SUPPORT)]
  private ?TwoFactorToken $twoFactorToken;

  #[Multiple(Group::class)]
  public array $groups;

  public function __construct(?int $id = null) {
    parent::__construct($id);
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

  public function isActive():bool {
    return $this->active;
  }

  public function isConfirmed():bool {
    return $this->confirmed;
  }

  public function __debugInfo(): array {
    return [
      'id' => $this->getId(),
      'username' => $this->name,
      'language' => isset($this->language) ? $this->language->getName() : null,
    ];
  }

  public function update(SQL $sql): bool {
    $this->lastOnline = new \DateTime();
    return $this->save($sql, ["lastOnline", "language"]);
  }

  public function setTwoFactorToken(TwoFactorToken $twoFactorToken) {
    $this->twoFactorToken = $twoFactorToken;
  }

  public function canAccess(\ReflectionClass|DatabaseEntity|string $entityOrClass, string $propertyName): bool {
    try {
      $reflectionClass = ($entityOrClass instanceof \ReflectionClass
        ? $entityOrClass
        : new \ReflectionClass($entityOrClass));

      $property = $reflectionClass->getProperty($propertyName);
      $visibility = DatabaseEntityHandler::getAttribute($property, Visibility::class);
      if ($visibility === null) {
        return true;
      }

      $visibilityType = $visibility->getType();
      if ($visibilityType === Visibility::NONE) {
        return false;
      } else if ($visibilityType === Visibility::BY_GROUP) {
        // allow access to own entity
        if ($entityOrClass instanceof User && $entityOrClass->getId() === $this->id) {
          return true;
        }

        // missing required group
        if (empty(array_intersect(array_keys($this->groups), $visibility->getGroups()))) {
          return false;
        }
      }

      return true;
    } catch (\Exception $exception) {
      return false;
    }
  }

  public function getDisplayName(): string {
    return !empty($this->fullName) ? $this->fullName : $this->name;
  }

  public static function buildSQLDisplayName(SQL $sql, string $joinColumn, string $alias = "user"): Alias {
    return new Alias(
      $sql->select(new Coalesce(
            new NullIf(new Column("User.full_name"), ""),
            new NullIf(new Column("User.name"), ""))
        )->from("User")->whereEq("User.id", new Column($joinColumn)),
      $alias);
  }
}