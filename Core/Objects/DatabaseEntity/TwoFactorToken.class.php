<?php

namespace Core\Objects\DatabaseEntity;

use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Attribute\ExtendingEnum;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
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
  private bool $authenticated;
  #[MaxLength(512)] private ?string $data;

  public function __construct(string $type, ?int $id = null, bool $confirmed = false) {
    parent::__construct($id);
    $this->id = $id;
    $this->type = $type;
    $this->confirmed = $confirmed;
    $this->authenticated = $_SESSION["2faAuthenticated"] ?? false;
    $this->data = null;
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

  public function isAuthenticated(): bool {
    return $this->authenticated;
  }

  public function confirm(SQL $sql): bool {
    $this->confirmed = true;
    return $this->save($sql) !== false;
  }
}