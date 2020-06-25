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

use Driver\SQL\Condition\CondRegex;
use Driver\SQL\Expression\Add;
use Driver\SQL\Strategy\Strategy;
use \Driver\SQL\Strategy\UpdateStrategy;

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

  // Connection Management
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

    if (mysqli_connect_errno()) {
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
    return true;
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
          $sqlParams[0] .= 'i';
          break;
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

  protected function getOnDuplicateStrategy(?Strategy $strategy, &$params) {
    if (is_null($strategy)) {
      return "";
    } else if ($strategy instanceof UpdateStrategy) {
      $updateValues = array();
      foreach($strategy->getValues() as $key => $value) {
        $leftColumn = $this->columnName($key);
        if ($value instanceof Column) {
          $columnName = $this->columnName($value->getName());
          $updateValues[] = "$leftColumn=VALUES($columnName)";
        } else if($value instanceof Add) {
          $columnName = $this->columnName($value->getColumn());
          $operator = $value->getOperator();
          $value = $value->getValue();
          $updateValues[] = "$leftColumn=$columnName$operator" . $this->addValue($value, $params);
        } else {
          $updateValues[] = "$leftColumn=" . $this->addValue($value, $params);
        }
      }

      return " ON DUPLICATE KEY UPDATE " . implode(",", $updateValues);
    } else {
      $strategyClass = get_class($strategy);
      $this->lastError = "ON DUPLICATE Strategy $strategyClass is not supported yet.";
      return false;
    }
  }

  protected function fetchReturning($res, string $returningCol) {
    $this->lastInsertId = mysqli_insert_id($this->connection);
  }

  public function getColumnDefinition(Column $column) {
    $columnName = $this->columnName($column->getName());
    $defaultValue = $column->getDefaultValue();

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
      $type = "LONGTEXT"; # some maria db setups don't allow JSON here…
      $defaultValue = NULL; # must be null :(
    } else {
      $this->lastError = "Unsupported Column Type: " . get_class($column);
      return NULL;
    }

    $notNull = $column->notNull() ? " NOT NULL" : "";
    if (!is_null($defaultValue) || !$column->notNull()) {
      $defaultValue = " DEFAULT " . $this->getValueDefinition($column->getDefaultValue());
    } else {
      $defaultValue = "";
    }

    return "$columnName $type$notNull$defaultValue";
  }

  public function getValueDefinition($value) {
    if (is_numeric($value)) {
      return $value;
    } else if(is_bool($value)) {
      return $value ? "TRUE" : "FALSE";
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
    if (is_array($table)) {
      $tables = array();
      foreach($table as $t) $tables[] = $this->tableName($t);
      return implode(",", $tables);
    } else {
      return "`$table`";
    }
  }

  protected function columnName($col) {
    if ($col instanceof Keyword) {
      return $col->getValue();
    } elseif(is_array($col)) {
      $columns = array();
      foreach($col as $c) $columns[] = $this->columnName($c);
      return implode(",", $columns);
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

  public function getStatus() {
    return mysqli_stat($this->connection);
  }
}
