<?php

namespace Driver\SQL;

use \Api\Parameter\Parameter;

use Driver\SQL\Column\Column;
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
use Driver\SQL\Strategy\UpdateStrategy;

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

  // Connection Management
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

  protected function getOnDuplicateStrategy(?Strategy $strategy, &$params) {
      if (!is_null($strategy)) {
        if ($strategy instanceof UpdateStrategy) {
              $updateValues = array();
              foreach($strategy->getValues() as $key => $value) {
                $leftColumn = $this->columnName($key);
                if ($value instanceof Column) {
                  $columnName = $this->columnName($value->getName());
                  $updateValues[] = "$leftColumn=EXCLUDED.$columnName";
                } else if ($value instanceof Add) {
                  $columnName = $this->columnName($value->getColumn());
                  $operator = $value->getOperator();
                  $value = $value->getValue();
                  $updateValues[] = "$leftColumn=$columnName$operator" . $this->addValue($value, $params);
                } else {
                  $updateValues[] = "$leftColumn=" . $this->addValue($value, $parameters);
                }
              }

              $conflictingColumns = $this->columnName($strategy->getConflictingColumns());
              $updateValues = implode(",", $updateValues);
              return " ON CONFLICT ($conflictingColumns) DO UPDATE SET $updateValues";
          } else {
            $strategyClass = get_class($strategy);
            $this->lastError = "ON DUPLICATE Strategy $strategyClass is not supported yet.";
           return false;
          }
      } else {
        return "";
      }
  }

  protected function getReturning(?string $columns) {
    return $columns ? (" RETURNING " . $this->columnName($columns)) : "";
  }

  protected function fetchReturning($res, string $returningCol) {
    $this->lastInsertId = $res[0][$returningCol];
  }

  // UGLY but.. what should i do?
  private function createEnum(EnumColumn $enumColumn) {
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

  public function getStatus() {
    $version = pg_version($this->connection)["client"] ?? "??";
    $status = pg_connection_status($this->connection);
    static $statusTexts = array(
      PGSQL_CONNECTION_OK => "PGSQL_CONNECTION_OK",
      PGSQL_CONNECTION_BAD => "PGSQL_CONNECTION_BAD",
    );

    return ($statusTexts[$status] ?? "Unknown") . " (v$version)";
  }

  protected function buildCondition($condition, &$params) {
    if($condition instanceof CondRegex) {
      $left = $condition->getLeftExp();
      $right = $condition->getRightExp();
      $left = ($left instanceof Column) ? $this->columnName($left->getName()) : $this->addValue($left, $params);
      $right = ($right instanceof Column) ? $this->columnName($right->getName()) : $this->addValue($right, $params);
      return $left . " ~ " . $right;
    } else {
      return parent::buildCondition($condition, $params);
    }
  }
}