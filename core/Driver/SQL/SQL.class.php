<?php

namespace Driver\SQL;

use \Driver\SQL\Constraint\Unique;
use \Driver\SQL\Constraint\PrimaryKey;
use \Driver\SQL\Constraint\ForeignKey;

abstract class SQL {

  protected $lastError;
  protected $connection;
  protected $connectionData;
  protected $lastInsertId;

  public function __construct($connectionData) {
    $this->connection = NULL;
    $this->lastError = 'Unknown Error';
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

  // TODO pull this function up
  public abstract function executeInsert($query);

  public function executeSelect($select) {

    $columns = $this->columnName($select->getColumns());
    $tables = $select->getTables();
    $params = array();

    if (!$tables) {
      return "SELECT $columns";
    }

    $tables = $this->tableName($tables);
    $where = $this->getWhereClause($select->getConditions(), $params);

    $joinStr = "";
    $joins = $select->getJoins();
    if (!empty($joins)) {
      foreach($joins as $join) {
        $type = $join->getType();
        $joinTable = $this->tableName($join->getTable());
        $columnA = $this->columnName($join->getColumnA());
        $columnB = $this->columnName($join->getColumnB());
        $joinStr .= " $type JOIN $joinTable ON $columnA=$columnB";
      }
    }

    $orderBy = "";
    $orderColumns = $select->getOrderBy();
    if (!empty($orderColumns)) {
      $orderBy = " ORDER BY " . $this->columnName($orderColumns);
      $orderBy .= ($select->isOrderedAscending() ? " ASC" : " DESC");
    }

    $limit = ($select->getLimit() > 0 ? (" LIMIT " . $select->getLimit()) : "");
    $offset = ($select->getOffset() > 0 ? (" OFFSET " . $select->getOffset()) : "");
    $query = "SELECT $columns FROM $tables$joinStr$where$orderBy$limit$offset";
    return $this->execute($query, $params, true);
  }

  public function executeDelete($delete) {

    $table = $this->tableName($delete->getTable());
    $where = $this->getWhereClause($delete->getConditions(), $params);

    $query = "DELETE FROM $table$where";
    return $this->execute($query);
  }

  public function executeTruncate($truncate) {
    return $this->execute("TRUNCATE " . $truncate->getTable());
  }

  public function executeUpdate($update) {

    $params = array();
    $table = $this->tableName($update->getTable());

    $valueStr = array();
    foreach($update->getValues() as $key => $val) {
      $valueStr[] = "$key=" . $this->addValue($val, $params);
    }
    $valueStr = implode(",", $valueStr);

    $where = $this->getWhereClause($update->getConditions(), $params);
    $query = "UPDATE $table SET $valueStr$where";
    return $this->execute($query, $params);
  }

  protected function getWhereClause($conditions, &$params) {
    if (!$conditions) {
      return "";
    } else {
      return " WHERE " . $this->buildCondition($conditions, $params);
    }
  }

  protected abstract function getColumnDefinition($column);

  public function getConstraintDefinition($constraint) {
    $columnName = $this->columnName($constraint->getColumnName());
    if ($constraint instanceof PrimaryKey) {
      return "PRIMARY KEY ($columnName)";
    } else if ($constraint instanceof Unique) {
      return "UNIQUE ($columnName)";
    } else if ($constraint instanceof ForeignKey) {
      $refTable = $this->tableName($constraint->getReferencedTable());
      $refColumn = $this->columnName($constraint->getReferencedColumn());
      $strategy = $constraint->onDelete();
      $code = "FOREIGN KEY ($columnName) REFERENCES $refTable ($refColumn)";
      if ($strategy instanceof SetDefaultStrategy) {
        $code .= " ON DELETE SET DEFAULT";
      } else if($strategy instanceof SetNullStrategy) {
        $code .= " ON DELETE SET NULL";
      } else if($strategy instanceof CascadeStrategy) {
        $code .= " ON DELETE CASCADE";
      }

      return $code;
    } else {
      $this->lastError = "Unsupported constraint type: " . get_class($strategy);
    }
  }

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
