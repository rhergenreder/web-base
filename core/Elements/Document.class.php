<?php

namespace Elements;

use Driver\SQL\SQL;
use Objects\User;

abstract class Document {

  protected User $user;
  protected bool $databaseRequired;
  private bool $cspEnabled;
  private ?string $cspNonce;

  public function __construct(User $user) {
    $this->user = $user;
    $this->cspEnabled = false;
    $this->cspNonce = null;
    $this->databaseRequired = true;
  }

  public function getSQL(): ?SQL {
    return $this->user->getSQL();
  }

  public function getUser(): User {
    return $this->user;
  }

  public function getCSPNonce(): ?string {
    return $this->cspNonce;
  }

  public function isCSPEnabled(): bool {
    return $this->cspEnabled;
  }

  public function enableCSP() {
    $this->cspEnabled = true;
    $this->cspNonce = generateRandomString(16, "base62");
  }

  public function getCode(): string {
    if ($this->databaseRequired) {
      $sql = $this->user->getSQL();
      if (is_null($sql)) {
        die("Database is not configured yet.");
      } else if (!$sql->isConnected()) {
        die("Database is not connected: " . $sql->getLastError());
      }
    }

    if ($this->cspEnabled) {
      $csp = ["default-src 'self'", "object-src 'none'", "base-uri 'self'", "style-src 'self' 'unsafe-inline'", "script-src 'nonce-$this->cspNonce'"];
      if ($this->user->getConfiguration()->getSettings()->isRecaptchaEnabled()) {
        $csp[] = "frame-src https://www.google.com/ 'self'";
      }

      $compiledCSP = implode(";", $csp);
      header("Content-Security-Policy: $compiledCSP;");
    }

    return "";
  }
}