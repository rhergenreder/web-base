<?php

namespace Objects;

class ConnectionData {

  private string $host;
  private int $port;
  private string $login;
  private string $password;
  private array $properties;

  public function __construct($host, $port, $login, $password) {
    $this->host = $host;
    $this->port = $port;
    $this->login = $login;
    $this->password = $password;
    $this->properties = array();
  }

  public function getProperties() {
    return $this->properties;
  }

  public function getProperty($key, $defaultValue='') {
    return $this->properties[$key] ?? $defaultValue;
  }

  public function setProperty($key, $val) {
    if(!is_string($val)) {
      return false;
    }

    $this->properties[$key] = $val;
    return true;
  }

  public function getHost() { return $this->host; }
  public function getPort() { return $this->port; }
  public function getLogin() { return $this->login; }
  public function getPassword() { return $this->password; }
}