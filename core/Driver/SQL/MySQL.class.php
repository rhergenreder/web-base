<?php

namespace Driver\SQL;

use \Api\Parameter\Parameter;

use \Driver\SQL\Column\Column;
use \Driver\SQL\Column\IntColumn;
use \Driver\SQL\Column\SerialColumn;
use \Driver\SQL\Column\StringColumn;
use \Driver\SQL\Column\EnumColumn;
use \Driver\SQL\Column\DateTimeColumn;
use Driver\SQL\Column\BoolColumn;
use Driver\SQL\Column\JsonColumn;

use \Driver\SQL\Strategy\CascadeStrategy;
use \Driver\SQL\Strategy\SetDefaultStrategy;
use \Driver\SQL\Strategy\SetNullStrategy;
use \Driver\SQL\Strategy\UpdateStrategy;

use \Driver\SQL\Constraint\Unique;
use \Driver\SQL\Constraint\PrimaryKey;
use \Driver\SQL\Constraint\ForeignKey;

class MySQL extends SQL {

  public function __construct($connectionData) {
     parent::__construct($connectionData);
  }

  public function checkRequirements() {
    return function_exists('mysqli_connect');
  }

  public function getDriverName() {
    return 'mysqli';
  }

  public function connect() {

    if(!is_null($this->connection)) {
      return true;
    }

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

    mysqli_set_charset($this->connection, $this->connectionData->getProperty('encoding', 'UTF-8'));
    return true;
  }

  public function disconnect() {
    if(is_null($this->connection)) {
      return true;
    }

    mysqli_close($this->connection);
  }

  public function getLastError() {
    $lastError = parent::getLastError();
    if (empty($lastError)) {
      $lastError = mysqli_error($this->connection);
    }

    return $lastError;
  }

  private function getPreparedParams($values) {
    $sqlParams = array('');
    foreach($values as $value) {
      $paramType = Parameter::parseType($value);
      switch($paramType) {
        case Parameter::TYPE_BOOLEAN:
          $value = $value ? 1 : 0;
        case Parameter::TYPE_INT:
          $sqlParams[0] .= 'i';
          break;
        case Parameter::TYPE_FLOAT:
          $sqlParams[0] .= 'd';
          break;
        case Parameter::TYPE_DATE:
          $value = $value->format('Y-m-d');
          $sqlParams[0] .= 's';
          break;
        case Parameter::TYPE_TIME:
          $value = $value->format('H:i:s');
          $sqlParams[0] .= 's';
          break;
        case Parameter::TYPE_DATE_TIME:
          $value = $value->format('Y-m-d H:i:s');
          $sqlParams[0] .= 's';
          break;
        case Parameter::TYPE_EMAIL:
        default:
          $sqlParams[0] .= 's';
      }

      $sqlParams[] = $value;
    }

    return $sqlParams;
  }

  protected function execute($query, $values = NULL, $returnValues = false) {

    $resultRows = array();
    $this->lastError = "";

    if (is_null($values) || empty($values)) {
      $res = mysqli_query($this->connection, $query);
      $success = $res !== FALSE;
      if ($success && $returnValues) {
        while($row = $res->fetch_assoc()) {
          $resultRows[] = $row;
        }
        $res->close();
      }
    } else if($stmt = $this->connection->prepare($query)) {

      $success = false;
      $sqlParams = $this->getPreparedParams($values);
      $tmp = array();
      foreach($sqlParams as $key => $value) $tmp[$key] = &$sqlParams[$key];
      if(call_user_func_array(array($stmt, "bind_param"), $tmp)) {
        if($stmt->execute()) {
          if ($returnValues) {
            $res = $stmt->get_result();
            if($res) {
              while($row = $res->fetch_assoc()) {
                $resultRows[] = $row;
              }
              $res->close();
              $success = true;
            } else {
              $this->lastError = "PreparedStatement::get_result failed: $stmt->error ($stmt->errno)";
            }
          } else {
            $success = true;
          }
        } else {
          $this->lastError = "PreparedStatement::execute failed: $stmt->error ($stmt->errno)";
        }
      } else {
        $this->lastError = "PreparedStatement::prepare failed: $stmt->error ($stmt->errno)";
      }

      $stmt->close();
    } else {
      $success = false;
    }

    return ($success && $returnValues) ? $resultRows : $success;
  }

  public function executeCreateTable($createTable) {
    $tableName = $createTable->getTableName();
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
    $query = "CREATE TABLE$ifNotExists `$tableName` ($entries)";
    return $this->execute($query);
  }

  public function executeInsert($insert) {
    $tableName = $insert->getTableName();
    $columns = $insert->getColumns();
    $rows = $insert->getRows();
    $onDuplicateKey = $insert->onDuplicateKey() ?? "";

    if (empty($rows)) {
      $this->lastError = "No rows to insert given.";
      return false;
    }

    if (is_null($columns) || empty($columns)) {
      $columns = "";
      $numColumns = count($rows[0]);
    } else {
      $numColumns = count($columns);
      $columns = " (`" . implode("`, `", $columns) . "`)";
    }

    $numRows = count($rows);
    $parameters = array();
    $values = implode(",", array_fill(0, $numRows, "(" . implode(",", array_fill(0, $numColumns, "?")) . ")"));

    foreach($rows as $row) {
      $parameters = array_merge($parameters, $row);
    }

    if ($onDuplicateKey) {
      if ($onDuplicateKey instanceof UpdateStrategy) {
        $updateValues = array();
        foreach($onDuplicateKey->getValues() as $key => $value) {
          if ($value instanceof Column) {
            $columnName = $value->getName();
            $updateValues[] = "`$key`=`$columnName`";
          } else {
            $updateValues[] = "`$key`=" . $this->addValue($value, $parameters);
          }
        }

        $onDuplicateKey = " ON DUPLICATE KEY UPDATE " . implode(",", $updateValues);
      } else {
        $strategy = get_class($onDuplicateKey);
        $this->lastError = "ON DUPLICATE Strategy $strategy is not supported yet.";
        return false;
      }
    }

    $query = "INSERT INTO `$tableName`$columns VALUES$values$onDuplicateKey";
    $success = $this->execute($query, $parameters);

    if($success) {
      $this->lastInsertId = mysqli_insert_id($this->connection);
    }

    return $success;
  }

  public function executeSelect($select) {

    $columns = array();
    foreach($select->getColumns() as $col) {
      $columns[] = $this->columnName($col);
    }

    $columns = implode(",", $columns);
    $tables = $select->getTables();
    $params = array();

    if (is_null($tables) || empty($tables)) {
      return "SELECT $columns";
    } else {
      $tables = implode(",", $tables);
    }

    $conditions = $select->getConditions();
    if (!empty($conditions)) {
      $condition = " WHERE " . $this->buildCondition($conditions, $params);
    } else {
      $condition = "";
    }

    $joinStr = "";
    $joins = $select->getJoins();
    if (!empty($joins)) {
      $joinStr = "";
      foreach($joins as $join) {
        $type = $join->getType();
        $joinTable = $join->getTable();
        $columnA = $join->getColumnA();
        $columnB = $join->getColumnB();
        $joinStr .= " $type JOIN $joinTable ON $columnA=$columnB";
      }
    }

    $orderBy = "";
    $limit = "";
    $offset = "";

    $query = "SELECT $columns FROM $tables$joinStr$condition$orderBy$limit$offset";
    return $this->execute($query, $params, true);
  }

  public function executeDelete($delete) {

    $table = $delete->getTable();
    $conditions = $delete->getConditions();
    if (!empty($conditions)) {
      $condition = " WHERE " . $this->buildCondition($conditions, $params);
    } else {
      $condition = "";
    }

    $query = "DELETE FROM $table$condition";
    return $this->execute($query);
  }

  public function executeTruncate($truncate) {
    return $this->execute("TRUNCATE " . $truncate->getTable());
  }

  public function executeUpdate($update) {

    $params = array();
    $table = $update->getTable();

    $valueStr = array();
    foreach($update->getValues() as $key => $val) {
      $valueStr[] = "$key=" . $this->addValue($val, $params);
    }
    $valueStr = implode(",", $valueStr);

    $conditions = $update->getConditions();
    if (!empty($conditions)) {
      $condition = " WHERE " . $this->buildCondition($conditions, $params);
    } else {
      $condition = "";
    }

    $query = "UPDATE $table SET $valueStr$condition";
    return $this->execute($query, $params);
  }

  public function getColumnDefinition($column) {
    $columnName = $column->getName();

    if ($column instanceof StringColumn) {
      $maxSize = $column->getMaxSize();
      if ($maxSize) {
        $type = "VARCHAR($maxSize)";
      } else {
        $type = "TEXT";
      }
    } else if($column instanceof SerialColumn) {
      $type = "INTEGER AUTO_INCREMENT";
    } else if($column instanceof IntColumn) {
      $type = "INTEGER";
    } else if($column instanceof DateTimeColumn) {
      $type = "DATETIME";
    } else if($column instanceof EnumColumn) {
      $values = array();
      foreach($column->getValues() as $value) {
        $values[] = $this->getValueDefinition($value);
      }

      $values = implode(",", $values);
      $type = "ENUM($values)";
    } else if($column instanceof BoolColumn) {
      $type = "BOOLEAN";
    } else if($column instanceof JsonColumn) {
      $type = "JSON";
    } else {
      $this->lastError = "Unsupported Column Type: " . get_class($column);
      return NULL;
    }

    $notNull = $column->notNull() ? " NOT NULL" : "";
    $defaultValue = "";
    if (!is_null($column->getDefaultValue()) || !$column->notNull()) {
      $defaultValue = " DEFAULT " . $this->getValueDefinition($column->getDefaultValue());
    }

    return "`$columnName` $type$notNull$defaultValue";
  }

  public function getConstraintDefinition($constraint) {
    $columnName = $constraint->getColumnName();
    if ($constraint instanceof PrimaryKey) {
      if (is_array($columnName)) $columnName = implode('`,`', $columnName);
      return "PRIMARY KEY (`$columnName`)";
    } else if ($constraint instanceof Unique) {
      if (is_array($columnName)) $columnName = implode('`,`', $columnName);
      return "UNIQUE (`$columnName`)";
    } else if ($constraint instanceof ForeignKey) {
      $refTable = $constraint->getReferencedTable();
      $refColumn = $constraint->getReferencedColumn();
      $strategy = $constraint->onDelete();
      $code = "FOREIGN KEY (`$columnName`) REFERENCES `$refTable` (`$refColumn`)";
      if ($strategy instanceof SetDefaultStrategy) {
        $code .= " ON DELETE SET DEFAULT";
      } else if($strategy instanceof SetNullStrategy) {
        $code .= " ON DELETE SET NULL";
      } else if($strategy instanceof CascadeStrategy) {
        $code .= " ON DELETE CASCADE";
      }

      return $code;
    }
  }

  // TODO: check this please..
  public function getValueDefinition($value) {
    if (is_numeric($value) || is_bool($value)) {
      return $value;
    } else if(is_null($value)) {
      return "NULL";
    } else if($value instanceof Keyword) {
      return $value->getValue();
    } else {
      $str = addslashes($value);
      return "'$str'";
    }
  }

  protected function addValue($val, &$params) {
    if ($val instanceof Keyword) {
      return $val->getValue();
    } else {
      $params[] = $val;
      return "?";
    }
  }

  protected function tableName($table) {
    return "`$table`";
  }

  protected function columnName($col) {
    if ($col instanceof Keyword) {
      return $col->getValue();
    } else {
      if (($index = strrpos($col, ".")) !== FALSE) {
        $tableName = $this->tableName(substr($col, 0, $index));
        $columnName = $this->columnName(substr($col, $index + 1));
        return "$tableName.$columnName";
      } else if(($index = stripos($col, " as ")) !== FALSE) {
        $columnName = $this->columnName(trim(substr($col, 0, $index)));
        $alias = trim(substr($col, $index + 4));
        return "$columnName as $alias";
      } else {
        return "`$col`";
      }
    }
  }

  public function currentTimestamp() {
    return new Keyword("NOW()");
  }

  public function count($col = NULL) {
    if (is_null($col)) {
      return new Keyword("COUNT(*) AS count");
    } else {
      return new Keyword("COUNT($col) AS count");
    }
  }

};
