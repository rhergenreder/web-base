<?php

namespace Objects\TwoFactor;
use Objects\ApiObject;

abstract class TwoFactorToken extends ApiObject {

  private ?int $id;
  private string $type;
  private bool $confirmed;
  private bool $authenticated;

  public function __construct(string $type, ?int $id = null, bool $confirmed = false) {
    $this->id = $id;
    $this->type = $type;
    $this->confirmed = $confirmed;
    $this->authenticated = $_SESSION["2faAuthenticated"] ?? false;
  }

  public function jsonSerialize(): array {
    return [
      "id" => $this->id,
      "type" => $this->type,
      "confirmed" => $this->confirmed,
      "authenticated" => $this->authenticated,
    ];
  }

  public abstract function getData(): string;

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

  public static function newInstance(string $type, string $data, ?int $id = null, bool $confirmed = false) {
    if ($type === TimeBasedTwoFactorToken::TYPE) {
      return new TimeBasedTwoFactorToken($data, $id, $confirmed);
    } else if ($type === KeyBasedTwoFactorToken::TYPE) {
      return new KeyBasedTwoFactorToken($data, $id, $confirmed);
    } else {
      // TODO: error message
      return null;
    }
  }

  public function isAuthenticated(): bool {
    return $this->authenticated;
  }
}