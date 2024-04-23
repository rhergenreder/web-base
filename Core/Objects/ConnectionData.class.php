<?php

namespace Core\Objects;

class ConnectionData {

  private string $host;
  private int $port;
  private string $login;
  private string $password;
  private array $properties;

  public function __construct(string $host, int $port, string $login, string $password) {
    $this->host = $host;
    $this->port = $port;
    $this->login = $login;
    $this->password = $password;
    $this->properties = [];
  }

  public function getProperties(): array {
    return $this->properties;
  }

  public function getProperty($key, $defaultValue='') {
    return $this->properties[$key] ?? $defaultValue;
  }

  public function setProperty($key, $val): bool {
    if (!is_scalar($val)) {
      return false;
    }

    $this->properties[$key] = $val;
    return true;
  }

  public function getHost(): string { return $this->host; }
  public function getPort(): int { return $this->port; }
  public function getLogin(): string { return $this->login; }
  public function getPassword(): string { return $this->password; }
}