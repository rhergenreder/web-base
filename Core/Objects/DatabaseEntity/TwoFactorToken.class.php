<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\Enum;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\TwoFactor\KeyBasedTwoFactorToken;
use Core\Objects\TwoFactor\TimeBasedTwoFactorToken;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;

abstract class TwoFactorToken extends DatabaseEntity {

  #[Enum('totp','fido')] private string $type;
  private bool $confirmed;
  private bool $authenticated;
  #[MaxLength(512)] private string $data;

  public function __construct(string $type, ?int $id = null, bool $confirmed = false) {
    parent::__construct($id);
    $this->id = $id;
    $this->type = $type;
    $this->confirmed = $confirmed;
    $this->authenticated = $_SESSION["2faAuthenticated"] ?? false;
  }

  public function jsonSerialize(): array {
    return [
      "id" => $this->getId(),
      "type" => $this->type,
      "confirmed" => $this->confirmed,
      "authenticated" => $this->authenticated,
    ];
  }

  public abstract function getData(): string;
  protected abstract function readData(string $data);

  public function preInsert(array &$row) {
    $row["data"] = $this->getData();
  }

  public function postFetch(SQL $sql, array $row) {
    parent::postFetch($sql, $row);
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

  public function getId(): int {
    return $this->id;
  }

  public static function newInstance(\ReflectionClass $reflectionClass, array $row) {
    if ($row["type"] === TimeBasedTwoFactorToken::TYPE) {
      return (new \ReflectionClass(TimeBasedTwoFactorToken::class))->newInstanceWithoutConstructor();
    } else if ($row["type"] === KeyBasedTwoFactorToken::TYPE) {
      return (new \ReflectionClass(KeyBasedTwoFactorToken::class))->newInstanceWithoutConstructor();
    } else {
      // TODO: error message
      return null;
    }
  }

  public function isAuthenticated(): bool {
    return $this->authenticated;
  }
}