<?php

namespace Objects;

class ConnectionData {

  private $host;
  private $port;
  private $login;
  private $password;
  private $properties;

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

  public function getProperty($key) {
    if(isset($this->properties[$key]))
      return $this->properties[$key];
    else
      return '';
  }

  public function setProperty($key, $val) {
    if(!is_string($val)) {
      return false;
    }

    $this->properties[$key] = $val;
  }

  public function getHost() { return $this->host; }
  public function getPort() { return $this->port; }
  public function getLogin() { return $this->login; }
  public function getPassword() { return $this->password; }
}

?>
