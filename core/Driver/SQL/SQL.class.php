<?php

namespace Driver\SQL;

abstract class SQL {

  protected $lastError;
  protected $connection;
  protected $connectionData;
  protected $lastInsertId;

  public function __construct($connectionData) {
    $this->connection = NULL;
    $this->lastError = 'Not connected';
    $this->connectionData = $connectionData;
    $this->lastInsertId = 0;
  }

  public function isConnected() {
    return !is_null($this->connection);
  }

  public function getLastError() {
    return trim($this->lastError);
  }

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

  // ####################
  // ### ABSTRACT METHODS
  // ####################

  // Misc
  public abstract function checkRequirements();
  public abstract function getDriverName();

  // Connection Managment
  public abstract function connect();
  public abstract function disconnect();

  // TODO: pull code duplicates up

  // Querybuilder
  public function executeCreateTable($createTable) {
    $tableName = $this->tableName($createTable->getTableName());
    $ifNotExists = $createTable->ifNotExists() ? " IF NOT EXISTS": "";

    $entries = array();
    foreach($createTable->getColumns() as $column) {
      $entries[] = ($tmp = $this->getColumnDefinition($column));
      if (is_null($tmp)) {
        return false;
      }
    }

    foreach($createTable->getConstraints() as $constraint) {
      $entries[] = ($tmp = $this->getConstraintDefinition($constraint));
      if (is_null($tmp)) {
        return false;
      }
    }

    $entries = implode(",", $entries);
    $query = "CREATE TABLE$ifNotExists $tableName ($entries)";
    return $this->execute($query);
  }

  public abstract function executeInsert($query);
  public abstract function executeSelect($query);
  public abstract function executeDelete($query);
  public abstract function executeTruncate($query);
  public abstract function executeUpdate($query);
  protected abstract function getColumnDefinition($column);
  protected abstract function getConstraintDefinition($constraint);
  protected abstract function getValueDefinition($val);
  protected abstract function addValue($val, &$params);

  protected abstract function tableName($table);
  protected abstract function columnName($col);

  // Special Keywords and functions
  public abstract function currentTimestamp();

  public function count($col = NULL) {
    if (is_null($col)) {
      return new Keyword("COUNT(*) AS count");
    } else {
      $col = $this->columnName($col);
      return new Keyword("COUNT($col) AS count");
    }
  }

  public function distinct($col) {
    $col = $this->columnName($col);
    return new Keyword("DISTINCT($col)");
  }

  // Statements
  protected abstract function execute($query, $values=NULL, $returnValues=false);

  protected function buildCondition($condition, &$params) {
    if ($condition instanceof \Driver\SQL\Condition\CondOr) {
      $conditions = array();
      foreach($condition->getConditions() as $cond) {
        $conditions[] = $this->buildCondition($cond, $params);
      }
      return "(" . implode(" OR ", $conditions) . ")";
    } else if ($condition instanceof \Driver\SQL\Condition\Compare) {
      $column = $this->columnName($condition->getColumn());
      $value = $condition->getValue();
      $operator = $condition->getOperator();
      return $column . $operator . $this->addValue($value, $params);
    } else if ($condition instanceof \Driver\SQL\Condition\CondBool) {
      return $this->columnName($condition->getValue());
    } else if (is_array($condition)) {
      if (count($condition) == 1) {
        return $this->buildCondition($condition[0], $params);
      } else {
        $conditions = array();
        foreach($condition as $cond) {
          $conditions[] = $this->buildCondition($cond, $params);
        }
        return implode(" AND ", $conditions);
      }
    }
  }

  public function setLastError($str) {
    $this->lastError = $str;
  }

  public function getLastInsertId() {
    return $this->lastInsertId;
  }

  public function close() {
    $this->disconnect();
    $this->connection = NULL;
  }

  public static function createConnection($connectionData) {
    $type = $connectionData->getProperty("type");
    if ($type === "mysql") {
      $sql = new MySQL($connectionData);
    } else if ($type === "postgres") {
      $sql = new PostgreSQL($connectionData);
    /*} else if ($type === "oracle") {
      // $sql = new OracleSQL($connectionData);
    */
    } else {
      return "Unknown database type";
    }

    if ($sql->checkRequirements()) {
      $sql->connect();
    }

    return $sql;
  }
}

?>
