<?php

namespace Driver\SQL;

abstract class SQL {

  protected $lastError;
  protected $connection;
  protected $connectionData;
  protected $lastInsertId;
  private $type;

  public function __construct($type, $connectionData) {
    $this->type = $type;
    $this->connection = NULL;
    $this->lastError = 'Not connected';
    $this->connectionData = $connectionData;
    $this->lastInsertId = 0;
  }

  public abstract function connect();
  public abstract function disconnect();

  public function isConnected() {
    return !is_null($this->connection);
  }

  public function getLastError() {
    return trim($this->lastError);
  }

  // public function executeQuery($query) {
  //   if(!$this->isConnected()) {
  //     $this->lastError = "Database is not connected yet.";
  //     return false;
  //   }
  //
  //   return $query->execute($this);
  //   // var_dump($generatedQuery);
  //   // return $this->execute($generatedQuery);
  // }

  public function createTable($tableName) {
    return new Query\CreateTable($this, $tableName);
  }

  public function insert($tableName, $columns=array()) {
    return new Query\Insert($this, $tableName, $columns);
  }

  public function select(...$columNames) {
    return new Query\Select($this, $columNames);
  }

  public function truncate($table) {
    return new Query\Truncate($this, $table);
  }

  public function delete($table) {
    return new Query\Delete($this, $table);
  }

  public function update($table) {
    return new Query\Update($this, $table);
  }

  // Querybuilder
  public abstract function executeCreateTable($query);
  public abstract function executeInsert($query);
  public abstract function executeSelect($query);
  public abstract function executeDelete($query);
  public abstract function executeTruncate($query);
  public abstract function executeUpdate($query);

  //
  public abstract function currentTimestamp();

  protected abstract function getColumnDefinition($column);
  protected abstract function getConstraintDefinition($constraint);
  protected abstract function getValueDefinition($val);
  protected abstract function buildCondition($conditions, &$params);

  // Execute
  protected abstract function execute($query, $values=NULL, $returnValues=false);

  public function setLastError($str) {
    $this->lastError = $str;
  }

  protected function addValue($val, &$params) {
    if ($val instanceof Keyword) {
      return $val->getValue();
    } else {
      $params[] = $val;
      return "?";
    }
  }

  /*public function getLastErrorNumber() {
    return mysqli_errno($this->connection);
  }*/

  public function getLastInsertId() {
    return $this->lastInsertId;
  }

  public function close() {
    if(!is_null($this->connection)) {
      $this->connection->close();
    }
  }

  /*public function getAffectedRows() {
    return $this->connection->affected_rows;
  }*/

  /*
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

    while (($success = $this->connection->next_result())) {
      if (!$this->connection->more_results()) break;
    }

    if(!$success) {
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
  */

  public static function createConnection($connectionData) {
    $type = $connectionData->getProperty("type");
    if ($type === "mysql") {
      $sql = new MySQL($connectionData);
    /*} else if ($type === "postgres") {
      // $sql = new PostgreSQL($connectionData);
    } else if ($type === "oracle") {
      // $sql = new OracleSQL($connectionData);
    */
    } else {
      return "Unknown database type";
    }

    $sql->connect();
    return $sql;
  }
}

?>
