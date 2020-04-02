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

  public function getLastError() {
    $lastError = parent::getLastError();
    if (empty($lastError)) {
      $lastError = pg_last_error($this->connection) . " " . pg_last_error($this->connection);
    }

    return $lastError;
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
  public function executeInsert($insert) {

    $tableName = $this->tableName($insert->getTableName());
    $columns = $insert->getColumns();
    $rows = $insert->getRows();
    $onDuplicateKey = $insert->onDuplicateKey() ?? "";

    if (empty($rows)) {
      $this->lastError = "No rows to insert given.";
      return false;
    }

    if (is_null($columns) || empty($columns)) {
      $columnStr = "";
    } else {
      $columnStr = " (" . $this->columnName($columns) . ")";
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
      /*if ($onDuplicateKey instanceof UpdateStrategy) {
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
      } else*/ {
        $strategy = get_class($onDuplicateKey);
        $this->lastError = "ON DUPLICATE Strategy $strategy is not supported yet.";
        return false;
      }
    }

    $returningCol = $insert->getReturning();
    $returning = $returningCol ? (" RETURNING " . $this->columnName($returningCol)) : "";

    $query = "INSERT INTO $tableName$columnStr VALUES$values$onDuplicateKey$returning";
    $res = $this->execute($query, $parameters, !empty($returning));
    $success = ($res !== FALSE);

    if($success && !empty($returning)) {
      $this->lastInsertId = $res[0][$returningCol];
    }

    return $success;
  }

  public function executeSelect($select) {

    $columns = $this->columnName($select->getColumns());
    $tables = $select->getTables();
    $params = array();

    if (is_null($tables) || empty($tables)) {
      return "SELECT $columns";
    } else {
      $tableStr = $this->tableName($tables);
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
        $joinTable = $this->tableName($join->getTable());
        $columnA = $this->columnName($join->getColumnA());
        $columnB = $this->columnName($join->getColumnB());
        $joinStr .= " $type JOIN $joinTable ON $columnA=$columnB";
      }
    }

    $orderBy = "";
    $limit = "";
    $offset = "";

    $query = "SELECT $columns FROM $tableStr$joinStr$condition$orderBy$limit$offset";
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

    $query = "DELETE FROM \"$table\"$condition";
    return $this->execute($query);
  }

  public function executeTruncate($truncate) {
    $table = $truncate->getTable();
    return $this->execute("TRUNCATE \"$table\"");
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

    $query = "UPDATE \"$table\" SET $valueStr$condition";
    return $this->execute($query, $params);
  }

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
    $columnName = $this->columnName($column->getName());

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

    return "$columnName $type$notNull$defaultValue";
  }

  protected function getConstraintDefinition($constraint) {
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
      $params[] = is_bool($val) ? ($val ? "TRUE" : "FALSE") : $val;
      return '$' . count($params);
    }
  }

  protected function tableName($table) {
    if (is_array($table)) {
      $tables = array();
      foreach($table as $t) $tables[] = $this->tableName($t);
      return implode(",", $tables);
    } else {
      return "\"$table\"";
    }
  }

  protected function columnName($col) {
    if ($col instanceof KeyWord) {
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
        $alias = $this->columnName(trim(substr($col, $index + 4)));
        return "$columnName as $alias";
      } else {
        return "\"$col\"";
      }
    }
  }

  // Special Keywords and functions
  public function currentTimestamp() {
    return new Keyword("CURRENT_TIMESTAMP");
  }
}
?>
