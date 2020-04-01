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

class PostgreSQL extends SQL {

  public function __construct($connectionData) {
     parent::__construct($connectionData);
  }

  public function checkRequirements() {
    return function_exists('pg_connect');
  }

  public function getDriverName() {
    return 'pgsql';
  }

  public function getLastError() {
    $lastError = parent::getLastError();
    if (empty($lastError)) {
      $lastError = pg_last_error($this->connection) . " " . pg_last_error($this->connection);
    }

    return $lastError;
  }

  // Connection Managment
  public function connect() {
    if(!is_null($this->connection)) {
      return true;
    }

    $config = array(
      "host" => $this->connectionData->getHost(),
      "port" => $this->connectionData->getPort(),
      "dbname" => $this->connectionData->getProperty('database', 'public'),
      "user" => $this->connectionData->getLogin(),
      "password" => $this->connectionData->getPassword()
    );

    $connectionString = array();
    foreach($config as $key => $val) {
      if (!empty($val)) {
        $connectionString[] = "$key=$val";
      }
    }

    $this->connection = @pg_connect(implode(" ", $connectionString));
    if (!$this->connection) {
      $this->lastError = "Failed to connect to Database";
      $this->connection = NULL;
      return false;
    }

    pg_set_client_encoding($this->connection, $this->connectionData->getProperty('encoding', 'UTF-8'));
    return true;
  }

  public function disconnect() {
    if(is_null($this->connection))
      return;

    pg_close($this->connection);
  }

  protected function execute($query, $values = NULL, $returnValues = false) {

    $this->lastError = "";
    $stmt_name = uniqid();
    $pgParams = array();

    if (!is_null($values)) {
      foreach($values as $value) {
        $paramType = Parameter::parseType($value);
        switch($paramType) {
          case Parameter::TYPE_DATE:
            $value = $value->format("Y-m-d");
            break;
          case Parameter::TYPE_TIME:
            $value = $value->format("H:i:s");
            break;
          case Parameter::TYPE_DATE_TIME:
            $value = $value->format("Y-m-d H:i:s");
            break;
          default:
            break;
        }

        $pgParams[] = $value;
      }
    }

    $stmt = @pg_prepare($this->connection, $stmt_name, $query);
    if ($stmt === FALSE) {
      return false;
    }

    $result = @pg_execute($this->connection, $stmt_name, $pgParams);
    if ($result === FALSE) {
      return false;
    }

    if ($returnValues) {
      $rows = pg_fetch_all($result);
      if ($rows === FALSE) {
        if (empty(trim($this->getLastError()))) {
          $rows = array();
        }
      }

      return $rows;
    } else {
      return true;
    }
  }

  // Querybuilder
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
    $query = "CREATE TABLE$ifNotExists \"$tableName\" ($entries)";
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
      $columns = " (\"" . implode("\", \"", $columns) . "\")";
    }

    $numRows = count($rows);
    $parameters = array();

    $values = array();
    foreach($rows as $row) {
      $rowPlaceHolder = array();
      foreach($row as $val) {
        $rowPlaceHolder[] = $this->addValue($val, $parameters);
      }

      $values[] = "(" . implode(",", $rowPlaceHolder) . ")";
    }

    $values = implode(",", $values);

    if ($onDuplicateKey) {
      if ($onDuplicateKey instanceof UpdateStrategy) {
        $updateValues = array();
        foreach($onDuplicateKey->getValues() as $key => $value) {
          if ($value instanceof Column) {
            $columnName = $value->getName();
            $updateValues[] = "\"$key\"=\"$columnName\"";
          } else {
            $updateValues[] = "\"$key\"=" . $this->addValue($value, $parameters);
          }
        }

        $onDuplicateKey = " ON CONFLICT DO UPDATE SET " . implode(",", $updateValues);
      } else {
        $strategy = get_class($onDuplicateKey);
        $this->lastError = "ON DUPLICATE Strategy $strategy is not supported yet.";
        return false;
      }
    }

    $returningCol = $insert->getReturning();
    $returning = $returningCol ? " RETURNING \"$returningCol\"" : "";

    $query = "INSERT INTO \"$tableName\"$columns VALUES$values$onDuplicateKey$returning";
    $res = $this->execute($query, $parameters, !empty($returning));
    $success = ($res !== FALSE);

    if($success && !empty($returning)) {
      $this->lastInsertId = $res[0][$returningCol];
    }

    return $success;
  }

  // TODO:
  public function executeSelect($query) { }
  public function executeDelete($query) { }
  public function executeTruncate($query) { }
  public function executeUpdate($query) { }

  // UGLY but.. what should i do?
  private function createEnum($enumColumn) {
    $typeName = $enumColumn->getName();
    if(!endsWith($typeName, "_type")) {
      $typeName = "${typeName}_type";
    }

    $values = array();
    foreach($enumColumn->getValues() as $value) {
      $values[] = $this->getValueDefinition($value);
    }

    $values = implode(",", $values);
    $query =
      "DO $$ BEGIN
        CREATE TYPE \"$typeName\" AS ENUM ($values);
      EXCEPTION
        WHEN duplicate_object THEN null;
      END $$;";

    $this->execute($query);
    return $typeName;
  }

  protected function getColumnDefinition($column) {
    $columnName = $column->getName();

    if ($column instanceof StringColumn) {
      $maxSize = $column->getMaxSize();
      if ($maxSize) {
        $type = "VARCHAR($maxSize)";
      } else {
        $type = "TEXT";
      }
    } else if($column instanceof SerialColumn) {
      $type = "SERIAL";
    } else if($column instanceof IntColumn) {
      $type = "INTEGER";
    } else if($column instanceof DateTimeColumn) {
      $type = "TIMESTAMP";
    } else if($column instanceof EnumColumn) {
      $type = $this->createEnum($column);
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

    return "\"$columnName\" $type$notNull$defaultValue";
  }

  protected function getConstraintDefinition($constraint) {
    $columnName = $constraint->getColumnName();
    if ($constraint instanceof PrimaryKey) {
      if (is_array($columnName)) $columnName = implode('","', $columnName);
      return "PRIMARY KEY (\"$columnName\")";
    } else if ($constraint instanceof Unique) {
      if (is_array($columnName)) $columnName = implode('","', $columnName);
      return "UNIQUE (\"$columnName\")";
    } else if ($constraint instanceof ForeignKey) {
      $refTable = $constraint->getReferencedTable();
      $refColumn = $constraint->getReferencedColumn();
      $strategy = $constraint->onDelete();
      $code = "FOREIGN KEY (\"$columnName\") REFERENCES \"$refTable\" (\"$refColumn\")";
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

  protected function getValueDefinition($value) {
    if (is_numeric($value)) {
      return $value;
    } else if(is_bool($value)) {
      return $value ? "TRUE" : "FALSE";
    } else if(is_null($value)) {
      return "NULL";
    } else if($value instanceof Keyword) {
      return $value->getValue();
    } else {
      $str = str_replace("'", "''", $value);
      return "'$str'";
    }
  }

  protected function addValue($val, &$params) {
    if ($val instanceof Keyword) {
      return $val->getValue();
    } else {
      $params[] = $val;
      return '$' . count($params);
    }
  }

  // Special Keywords and functions
  public function currentTimestamp() {
    return "CURRENT_TIMESTAMP";
  }
}
?>
