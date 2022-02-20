<?php

namespace Objects;

abstract class TwoFactorToken extends ApiObject {

  private ?int $id;
  private string $type;
  private string $secret;
  private bool $confirmed;
  private bool $authenticated;

  public function __construct(string $type, string $secret, ?int $id = null, bool $confirmed = false) {
    $this->id = $id;
    $this->type = $type;
    $this->secret = $secret;
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

  public function authenticate() {
    $this->authenticated = true;
    $_SESSION["2faAuthenticated"] = true;
  }

  public function getType(): string {
    return $this->type;
  }

  public function getSecret(): string {
    return $this->secret;
  }

  public function isConfirmed(): bool {
    return $this->confirmed;
  }

  public function getId(): int {
    return $this->id;
  }

  public static function newInstance(string $type, string $secret, ?int $id = null, bool $confirmed = false) {
    if ($type === TimeBasedTwoFactorToken::TYPE) {
      return new TimeBasedTwoFactorToken($secret, $id, $confirmed);
    } else {
      // TODO: error message
      return null;
    }
  }

  public function isAuthenticated(): bool {
    return $this->authenticated;
  }
}