<?php

namespace Core\Driver\SQL;

use Core\API\Parameter\Parameter;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Column\IntColumn;
use Core\Driver\SQL\Column\NumericColumn;
use Core\Driver\SQL\Column\SerialColumn;
use Core\Driver\SQL\Column\StringColumn;
use Core\Driver\SQL\Column\EnumColumn;
use Core\Driver\SQL\Column\DateTimeColumn;
use Core\Driver\SQL\Column\BoolColumn;
use Core\Driver\SQL\Column\JsonColumn;

use Core\Driver\SQL\Condition\Compare;
use Core\Driver\SQL\Condition\CondLike;
use Core\Driver\SQL\Condition\CondRegex;
use Core\Driver\SQL\Expression\Add;
use Core\Driver\SQL\Expression\Count;
use Core\Driver\SQL\Expression\CurrentTimeStamp;
use Core\Driver\SQL\Expression\Expression;
use Core\Driver\SQL\Query\CreateProcedure;
use Core\Driver\SQL\Query\CreateTrigger;
use Core\Driver\SQL\Query\Insert;
use Core\Driver\SQL\Query\Query;
use Core\Driver\SQL\Strategy\Strategy;
use Core\Driver\SQL\Strategy\UpdateStrategy;
use Core\Driver\SQL\Type\CurrentColumn;
use Core\Driver\SQL\Type\CurrentTable;
use Core\Driver\SQL\Type\Trigger;

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
    if (!is_null($this->connection)) {
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
    foreach ($config as $key => $val) {
      if (!empty($val)) {
        $connectionString[] = "$key=$val";
      }
    }

    $this->connection = @pg_connect(implode(" ", $connectionString), PGSQL_CONNECT_FORCE_NEW);
    if (!$this->connection) {
      $this->lastError = $this->logger->severe("Failed to connect to Database");
      $this->connection = NULL;
      return false;
    }

    pg_set_client_encoding($this->connection, $this->connectionData->getProperty('encoding', 'UTF-8'));
    return true;
  }

  public function disconnect() {
    if (is_null($this->connection))
      return;

    @pg_close($this->connection);
  }

  public function getLastError(): string {
    $lastError = parent::getLastError();
    if (empty($lastError)) {
      $lastError = trim(pg_last_error($this->connection) . " " . pg_last_error($this->connection));
    }

    return $lastError;
  }

  /**
   * @return mixed
   */
  protected function execute($query, $values = NULL, int $fetchType = self::FETCH_NONE, int $logLevel = Logger::LOG_LEVEL_ERROR) {

    $this->lastError = "";
    $stmt_name = uniqid();
    $pgParams = array();

    if ($logLevel === Logger::LOG_LEVEL_DEBUG) {
      $this->logger->debug("query: " . $query . ", args: " . json_encode($values), false);
    }

    if (!is_null($values)) {
      foreach ($values as $value) {
        $paramType = Parameter::parseType($value);
        switch ($paramType) {
          case Parameter::TYPE_DATE:
            $value = $value->format("Y-m-d");
            break;
          case Parameter::TYPE_TIME:
            $value = $value->format("H:i:s");
            break;
          case Parameter::TYPE_DATE_TIME:
            $value = $value->format("Y-m-d H:i:s");
            break;
          case Parameter::TYPE_ARRAY:
            $value = json_encode($value);
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

    switch ($fetchType) {
      case self::FETCH_NONE:
        return true;
      case self::FETCH_ONE:
        return pg_fetch_assoc($result);
      case self::FETCH_ALL:
        $rows = pg_fetch_all($result);
        if ($rows === FALSE) {
          if (empty(trim($this->getLastError()))) {
            $rows = array();
          }
        }
        return $rows;
      case self::FETCH_ITERATIVE:
        return new RowIteratorPostgreSQL($result);
    }
  }

  public function getOnDuplicateStrategy(?Strategy $strategy, &$params): ?string {
    if (!is_null($strategy)) {
      if ($strategy instanceof UpdateStrategy) {
        $updateValues = array();
        foreach ($strategy->getValues() as $key => $value) {
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
        $this->lastError = $this->logger->error("ON DUPLICATE Strategy $strategyClass is not supported yet.");
        return null;
      }
    } else {
      return "";
    }
  }

  public function getReturning(?string $columns): string {
    return $columns ? (" RETURNING " . $this->columnName($columns)) : "";
  }

  public function executeQuery(Query $query, int $fetchType = self::FETCH_NONE) {

    if ($query instanceof Insert && !empty($query->getReturning())) {
      $fetchType = self::FETCH_ONE;
    }

    return parent::executeQuery($query, $fetchType);
  }

  protected function fetchReturning($res, string $returningCol) {
    $this->lastInsertId = $res[0][$returningCol];
  }

  // UGLY but.. what should i do?
  private function createEnum(EnumColumn $enumColumn, string $typeName): string {
    $values = array();
    foreach ($enumColumn->getValues() as $value) {
      $values[] = $this->getValueDefinition($value);
    }

    $values = implode(",", $values);
    $query =
      "DO $$ BEGIN
        CREATE TYPE \"$typeName\" AS ENUM ($values);
      EXCEPTION
        WHEN duplicate_object THEN null;
      END $$;";

    return $this->execute($query);
  }

  public function getColumnType(Column $column): ?string {
    if ($column instanceof StringColumn) {
      $maxSize = $column->getMaxSize();
      if ($maxSize) {
        return "VARCHAR($maxSize)";
      } else {
        return "TEXT";
      }
    } else if ($column instanceof SerialColumn) {
      return "SERIAL";
    } else if ($column instanceof IntColumn) {
      return $column->getType();
    } else if ($column instanceof DateTimeColumn) {
      return "TIMESTAMP";
    } else if ($column instanceof EnumColumn) {
      $typeName = $column->getName();
      if (!endsWith($typeName, "_type")) {
        $typeName = "${typeName}_type";
      }
      return $typeName;
    } else if ($column instanceof BoolColumn) {
      return "BOOLEAN";
    } else if ($column instanceof JsonColumn) {
      return "JSON";
    } else if ($column instanceof NumericColumn) {
      $digitsDecimal = $column->getDecimalDigits();
      $type = $column->getTypeName();
      if ($digitsDecimal !== null) {
        if ($type === "double") {
          $type = "float"; // postgres doesn't know about double :/
        }
        return "$type($digitsDecimal)";
      } else {
        return $type;
      }
    } else {
      $this->lastError = $this->logger->error("Unsupported Column Type: " . get_class($column));
      return NULL;
    }
  }

  public function getColumnDefinition($column): ?string {
    $columnName = $this->columnName($column->getName());

    $type = $this->getColumnType($column);
    if (!$type) {
      return null;
    } else if ($column instanceof EnumColumn) {
      if (!$this->createEnum($column, $type)) {
        return null;
      }
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
    } else if (is_bool($value)) {
      return $value ? "TRUE" : "FALSE";
    } else if (is_null($value)) {
      return "NULL";
    } else if ($value instanceof Keyword) {
      return $value->getValue();
    } else if ($value instanceof CurrentTimeStamp) {
      return "CURRENT_TIMESTAMP";
    } else {
      $str = str_replace("'", "''", $value);
      return "'$str'";
    }
  }

  public function addValue($val, &$params = NULL, bool $unsafe = false) {
    // I don't remember we need this here?
    /*if ($val instanceof CurrentTable) {
      return "TG_TABLE_NAME";
    } else if ($val instanceof CurrentColumn) {
      return "NEW." . $this->columnName($val->getName());
    } else */if ($val instanceof Expression) {
      return $val->getExpression($this, $params);
    } else {
      if ($unsafe) {
        return $this->getUnsafeValue($val);
      } else {
        $params[] = is_bool($val) ? ($val ? "TRUE" : "FALSE") : $val;
        return '$' . count($params);
      }
    }
  }

  public function tableName($table): string {
    if (is_array($table)) {
      $tables = array();
      foreach ($table as $t) $tables[] = $this->tableName($t);
      return implode(",", $tables);
    } else {
      $parts = explode(" ", $table);
      if (count($parts) === 2) {
        list ($name, $alias) = $parts;
        return "\"$name\" $alias";
      } else {
        $parts = explode(".", $table);
        return implode(".", array_map(function ($n) {
          return "\"$n\"";
        }, $parts));
      }
    }
  }

  public function columnName($col): string {
    if ($col instanceof KeyWord) {
      return $col->getValue();
    } elseif (is_array($col)) {
      $columns = array_map(function ($c) {
        return $this->columnName($c);
      }, $col);
      return implode(",", $columns);
    } else {
      if (($index = strrpos($col, ".")) !== FALSE) {
        $tableName = $this->tableName(substr($col, 0, $index));
        $columnName = $this->columnName(substr($col, $index + 1));
        return "$tableName.$columnName";
      } else if (($index = stripos($col, " as ")) !== FALSE) {
        $columnName = $this->columnName(trim(substr($col, 0, $index)));
        $alias = $this->columnName(trim(substr($col, $index + 4)));
        return "$columnName as $alias";
      } else {
        return "\"$col\"";
      }
    }
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

  public function buildCondition($condition, &$params) {
    if ($condition instanceof CondRegex) {
      $left = $condition->getLeftExp();
      $right = $condition->getRightExp();
      $left = ($left instanceof Column) ? $this->columnName($left->getName()) : $this->addValue($left, $params);
      $right = ($right instanceof Column) ? $this->columnName($right->getName()) : $this->addValue($right, $params);
      return $left . " ~ " . $right;
    } else {
      return parent::buildCondition($condition, $params);
    }
  }

  private function createTriggerProcedure(string $name, array $statements) {
    $params = [];
    $query = "CREATE OR REPLACE FUNCTION $name() RETURNS TRIGGER AS \$table\$ BEGIN ";
    foreach ($statements as $stmt) {
      if ($stmt instanceof Keyword) {
        $query .= $stmt->getValue() . ";";
      } else {
        $query .= $stmt->build($this, $params) . ";";
      }
    }
    $query .= "END;";
    $query .= "\$table\$ LANGUAGE plpgsql;";

    return $this->execute($query, $params);
  }

  public function createTriggerBody(CreateTrigger $trigger, array $params = []): ?string {
    $procName = $this->tableName($trigger->getProcedure()->getName());
    return "EXECUTE PROCEDURE $procName()";
  }

  public function getProcedureHead(CreateProcedure $procedure): ?string {
    $name = $this->tableName($procedure->getName());
    $returns = $procedure->getReturns() ?? "";
    $paramDefs = [];

    if (!($procedure->getReturns() instanceof Trigger)) {
      foreach ($procedure->getParameters() as $parameter) {
        $paramDefs[] = $parameter->getName() . " " . $this->getColumnType($parameter);
      }
    }

    $paramDefs = implode(",", $paramDefs);
    if ($returns) {
      if ($returns instanceof Column) {
        $returns = " RETURNS " . $this->getColumnType($returns);
      } else if ($returns instanceof Keyword) {
        $returns = " RETURNS " . $returns->getValue();
      }
    }

    return "CREATE OR REPLACE FUNCTION $name($paramDefs)$returns AS $$";
  }

  public function getProcedureTail(): string {
    return "$$ LANGUAGE plpgsql;";
  }

  public function getProcedureBody(CreateProcedure $procedure): string {
    $statements = parent::getProcedureBody($procedure);
    if ($procedure->getReturns() instanceof Trigger) {
      $statements .= "RETURN NEW;";
    }
    return $statements;
  }

  protected function buildUnsafe(Query $statement): string {
    $params = [];
    $query = $statement->build($params);

    foreach ($params as $index => $value) {
      $value = $this->getUnsafeValue($value);
      $query = preg_replace("\$$index", $value, $query, 1);
    }

    return $query;
  }

  public function tableExists(string $tableName): bool {
    $tableSchema = $this->connectionData->getProperty("database");
    $res = $this->select(new Count())
      ->from("pg_tables")
      ->whereEq("tablename", $tableName)
      ->whereEq("schemaname", $tableSchema)
      ->execute();

    return $res && $res[0]["count"] > 0;
  }



  public function listTables(): ?array {
    $tableSchema = $this->connectionData->getProperty("database");
    $res = $this->select("tablename")
      ->from("pg_tables")
      ->where(new Compare("schemaname", $tableSchema))
      ->execute();

    if ($res !== false) {
      $tableNames = [];

      foreach ($res as $row) {
        $tableNames[] = $row["tablename"];
      }

      return $tableNames;
    }

    return null;
  }
}

class RowIteratorPostgreSQL extends RowIterator {

  public function __construct($resultSet, bool $useCache = false) {
    parent::__construct($resultSet, false);  // caching not needed
  }

  protected function getNumRows(): int {
    return pg_num_rows($this->resultSet);
  }

  public function rewind() {
    $this->rowIndex = 0;
  }

  protected function fetchRow(int $index): array {
    return pg_fetch_assoc($this->resultSet, $index);
  }
}