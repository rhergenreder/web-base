<?php

namespace Elements;

use Configuration\Settings;
use Driver\SQL\SQL;
use Objects\Router\Router;
use Objects\User;

abstract class Document {

  protected Router $router;
  protected bool $databaseRequired;
  private bool $cspEnabled;
  private ?string $cspNonce;
  private array $cspWhitelist;
  private string $domain;

  public function __construct(Router $router) {
    $this->router = $router;
    $this->cspEnabled = false;
    $this->cspNonce = null;
    $this->databaseRequired = true;
    $this->cspWhitelist = [];
    $this->domain = $this->getSettings()->getBaseUrl();
  }

  public function getUser(): User {
    return $this->router->getUser();
  }

  public function getSQL(): ?SQL {
    return $this->getUser()->getSQL();
  }

  public function getSettings(): Settings {
    return $this->getUser()->getConfiguration()->getSettings();
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

  public function getRouter(): Router {
    return $this->router;
  }

  protected function addCSPWhitelist(string $path) {
    $this->cspWhitelist[] = $this->domain . $path;
  }

  public function getCode(array $params = []): string {
    if ($this->databaseRequired) {
      $sql = $this->getSQL();
      if (is_null($sql)) {
        die("Database is not configured yet.");
      } else if (!$sql->isConnected()) {
        die("Database is not connected: " . $sql->getLastError());
      }
    }

    if ($this->cspEnabled) {

      $cspWhiteList = implode(" ", $this->cspWhitelist);

      $csp = [
        "default-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data:",
        "script-src $cspWhiteList 'nonce-$this->cspNonce'"
      ];
      if ($this->getSettings()->isRecaptchaEnabled()) {
        $csp[] = "frame-src https://www.google.com/ 'self'";
      }

      $compiledCSP = implode("; ", $csp);
      header("Content-Security-Policy: $compiledCSP;");
    }

    return "";
  }
}