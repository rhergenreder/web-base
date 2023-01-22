<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\ExtendingEnum;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Attribute\Transient;
use Core\Objects\DatabaseEntity\Attribute\Visibility;
use Core\Objects\TwoFactor\KeyBasedTwoFactorToken;
use Core\Objects\TwoFactor\TimeBasedTwoFactorToken;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;

abstract class TwoFactorToken extends DatabaseEntity {

  const TWO_FACTOR_TOKEN_TYPES = [
    "totp" => TimeBasedTwoFactorToken::class,
    "fido" => KeyBasedTwoFactorToken::class,
  ];

  #[ExtendingEnum(self::TWO_FACTOR_TOKEN_TYPES)] private string $type;
  private bool $confirmed;

  #[Transient]
  private bool $authenticated;

  #[MaxLength(512)]
  #[Visibility(Visibility::NONE)]
  private ?string $data;

  public function __construct(string $type, ?int $id = null, bool $confirmed = false) {
    parent::__construct($id);
    $this->id = $id;
    $this->type = $type;
    $this->confirmed = $confirmed;
    $this->authenticated = $_SESSION["2faAuthenticated"] ?? false;
    $this->data = null;
  }

  public abstract function getData(): string;
  protected abstract function readData(string $data);

  public function preInsert(array &$row) {
    $row["data"] = $this->getData();
  }

  public function postFetch(SQL $sql, array $row) {
    parent::postFetch($sql, $row);
    $this->authenticated = $_SESSION["2faAuthenticated"] ?? false;
    $this->readData($row["data"]);
  }

  public function authenticate() {
    $this->authenticated = true;
    $_SESSION["2faAuthenticated"] = true;
  }

  public function getType(): string {
    return $this->type;
  }

  public function isConfirmed(): bool {
    return $this->confirmed;
  }

  public function isAuthenticated(): bool {
    return $this->authenticated;
  }

  public function confirm(SQL $sql): bool {
    $this->confirmed = true;
    return $this->save($sql, ["confirmed"]) !== false;
  }

  public function jsonSerialize(?array $propertyNames = null): array {
    $jsonData = parent::jsonSerialize($propertyNames);

    if ($propertyNames === null || in_array("authenticated", $propertyNames)) {
      $jsonData["authenticated"] = $this->authenticated;
    }

    return $jsonData;
  }

  public static function newInstance(\ReflectionClass $reflectionClass, array $row) {
    $type = $row["type"] ?? null;
    if ($type === "totp") {
      return (new \ReflectionClass(TimeBasedTwoFactorToken::class))->newInstanceWithoutConstructor();
    } else if ($type === "fido") {
      return (new \ReflectionClass(KeyBasedTwoFactorToken::class))->newInstanceWithoutConstructor();
    } else {
      return parent::newInstance($reflectionClass, $row);
    }
  }
}