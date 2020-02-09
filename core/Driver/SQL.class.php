<?php

namespace Driver;

class SQL {

  public $connection;
  public $lastError;
  private $connectionData;

  public function __construct($connectionData) {
    $this->connection = NULL;
    $this->lastError = 'Not connected';
    $this->connectionData = $connectionData;
  }

  public function connect() {
    if(!is_null($this->connection))
      return true;

    @$this->connection = mysqli_connect(
      $this->connectionData->getHost(),
      $this->connectionData->getLogin(),
      $this->connectionData->getPassword(),
      $this->connectionData->getProperty('database'),
      $this->connectionData->getPort()
    );

    if (mysqli_connect_errno($this->connection)) {
      $this->lastError = "Failed to connect to MySQL: " . mysqli_connect_error();
      $this->connection = NULL;
      return false;
    }

    mysqli_set_charset($this->connection, $this->connectionData->getProperty('encoding'));
    return true;
  }

  public function disconnect() {
    if(is_null($this->connection))
      return;

    mysqli_close($this->connection);
    $this->connection = NULL;
  }

  public function isConnected() {
    return !is_null($this->connection);
  }

  public function getLastError() {
    return empty(trim($this->lastError)) ? mysqli_error($this->connection) . " " . $this->getLastErrorNumber() : trim($this->lastError);
  }

  public function setLastError($str) {
    $this->lastError = $str;
  }

  public function getLastErrorNumber() {
    return mysqli_errno($this->connection);
  }

  public function getLastInsertId() {
    return $this->connection->insert_id;
  }

  public function close() {
    if(!is_null($this->connection)) {
      $this->connection->close();
    }
  }

  public function getAffectedRows() {
    return $this->connection->affected_rows;
  }

  public function execute($query) {
    if(!$this->isConnected()) {
      return false;
    }

    if(!mysqli_query($this->connection, $query)) {
      $this->lastError = mysqli_error($this->connection);
      return false;
    }

    return true;
  }

  public function executeMulti($queries) {
    if(!$this->isConnected()) {
      return false;
    }

    if(!$this->connection->multi_query($queries)) {
      $this->lastError = mysqli_error($this->connection);
      return false;
    }

    return true;
  }

  public function query($query) {
    if(!$this->isConnected()) {
      return false;
    }

    $res = mysqli_query($this->connection, $query);
    if(!$res) {
      $this->lastError = mysqli_error($this->connection);
      return false;
    }

    return $res;
  }

  public static function createConnection($connectionData) {
    $sql = new SQL($connectionData);
    $sql->connect();
    return $sql;
  }
}

?>
