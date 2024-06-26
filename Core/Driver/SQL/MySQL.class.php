<?php

namespace Core\Driver\SQL;

use Core\API\Parameter\Parameter;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Condition\Compare;
use Core\Driver\SQL\Condition\CondLike;
use Core\Driver\SQL\Expression\Count;
use DateTime;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Column\IntColumn;
use Core\Driver\SQL\Column\NumericColumn;
use Core\Driver\SQL\Column\SerialColumn;
use Core\Driver\SQL\Column\StringColumn;
use Core\Driver\SQL\Column\EnumColumn;
use Core\Driver\SQL\Column\DateTimeColumn;
use Core\Driver\SQL\Column\BoolColumn;
use Core\Driver\SQL\Column\JsonColumn;

use Core\Driver\SQL\Expression\Add;
use Core\Driver\SQL\Expression\CurrentTimeStamp;
use Core\Driver\SQL\Expression\Expression;
use Core\Driver\SQL\Query\CreateProcedure;
use Core\Driver\SQL\Query\CreateTrigger;
use Core\Driver\SQL\Query\Query;
use Core\Driver\SQL\Strategy\Strategy;
use Core\Driver\SQL\Strategy\UpdateStrategy;
use Core\Driver\SQL\Type\CurrentColumn;
use Core\Driver\SQL\Type\CurrentTable;
use Core\Driver\SQL\Type\Trigger;

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

    if (!is_null($this->connection)) {
      return true;
    }

    try {
      $this->connection = @mysqli_connect(
        $this->connectionData->getHost(),
        $this->connectionData->getLogin(),
        $this->connectionData->getPassword(),
        $this->connectionData->getProperty('database'),
        $this->connectionData->getPort()
      );

      if (mysqli_connect_errno()) {
        $this->lastError = $this->logger->severe("Failed to connect to MySQL: " . mysqli_connect_error());
        $this->connection = NULL;
        return false;
      }

      mysqli_set_charset($this->connection, $this->connectionData->getProperty('encoding', 'UTF8'));
      return true;
    } catch (\Exception $ex) {
      $this->lastError = $this->logger->severe("Failed to connect to MySQL: " . mysqli_connect_error());
      $this->connection = NULL;
      return false;
    }
  }

  public function disconnect() {
    if (is_null($this->connection)) {
      return true;
    }

    mysqli_close($this->connection);
    return true;
  }

  public function getLastError(): string {
    $lastError = parent::getLastError();
    if (empty($lastError)) {
      $lastError = mysqli_error($this->connection);
    }

    return $lastError;
  }

  private function getPreparedParams($values): array {
    $sqlParams = array('');
    foreach ($values as $value) {
      $paramType = Parameter::parseType($value, true);  // TODO: is strict type checking really correct here?
      switch ($paramType) {
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
          if ($value instanceof DateTime) {
            $value = $value->format('Y-m-d');
          }
          $sqlParams[0] .= 's';
          break;
        case Parameter::TYPE_TIME:
          if ($value instanceof DateTime) {
            $value = $value->format('H:i:s');
          }
          $sqlParams[0] .= 's';
          break;
        case Parameter::TYPE_DATE_TIME:
          if ($value instanceof DateTime) {
            $value = $value->format('Y-m-d H:i:s');
          }
          $sqlParams[0] .= 's';
          break;
        case Parameter::TYPE_ARRAY:
          $value = json_encode($value);
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

  /**
   * @return mixed
   */
  protected function execute($query, $values = NULL, int $fetchType = self::FETCH_NONE, int $logLevel = Logger::LOG_LEVEL_ERROR) {

    $result = null;
    $this->lastError = "";
    $stmt = null;
    $res = null;
    $success = false;

    if ($logLevel === Logger::LOG_LEVEL_DEBUG) {
      $this->logger->debug("query: " . $query . ", args: " . json_encode($values), false);
    }

    try {
      if (empty($values)) {
        $res = mysqli_query($this->connection, $query);
        $success = ($res !== FALSE);
        if ($success) {
          switch ($fetchType) {
            case self::FETCH_NONE:
              $result = true;
              break;
            case self::FETCH_ONE:
              $result = $res->fetch_assoc();
              break;
            case self::FETCH_ALL:
              $result = $res->fetch_all(MYSQLI_ASSOC);
              break;
            case self::FETCH_ITERATIVE:
              $result = new RowIteratorMySQL($res);
              break;
          }
        }
      } else if ($stmt = $this->connection->prepare($query)) {
        $sqlParams = $this->getPreparedParams($values);
        if ($stmt->bind_param(...$sqlParams)) {
          if ($stmt->execute()) {
            if ($fetchType === self::FETCH_NONE) {
              $result = true;
              $success = true;
            } else {
              $res = $stmt->get_result();
              if ($res) {
                switch ($fetchType) {
                  case self::FETCH_ONE:
                    $result = $res->fetch_assoc();
                    break;
                  case self::FETCH_ALL:
                    $result = $res->fetch_all(MYSQLI_ASSOC);
                    break;
                  case self::FETCH_ITERATIVE:
                    $result = new RowIteratorMySQL($res);
                    break;
                }
                $success = true;
              } else {
                $this->lastError = $this->logger->error("PreparedStatement::get_result failed: $stmt->error ($stmt->errno)");
              }
            }
          } else {
            $this->lastError = $this->logger->error("PreparedStatement::execute failed: $stmt->error ($stmt->errno)");
          }
        } else {
          $this->lastError = $this->logger->error("PreparedStatement::prepare failed: $stmt->error ($stmt->errno)");
        }
      }
    } catch (\mysqli_sql_exception $exception) {
      if ($logLevel >= Logger::LOG_LEVEL_ERROR) {
        $this->lastError = $this->logger->error("MySQL::execute failed: " .
          ($stmt !== null
            ? "$stmt->error ($stmt->errno)"
            : $exception->getMessage()));
      }
    } finally {

      if ($res !== null && !is_bool($res) && $fetchType !== self::FETCH_ITERATIVE) {
        $res->close();
      }

      if ($stmt !== null && !is_bool($stmt)) {
        $stmt->close();
      }

    }

    return $success ? $result : false;
  }

  public function getOnDuplicateStrategy(?Strategy $strategy, &$params): ?string {
    if (is_null($strategy)) {
      return "";
    } else if ($strategy instanceof UpdateStrategy) {
      $updateValues = array();
      foreach ($strategy->getValues() as $key => $value) {
        $leftColumn = $this->columnName($key);
        if ($value instanceof Column) {
          $columnName = $this->columnName($value->getName());
          $updateValues[] = "$leftColumn=VALUES($columnName)";
        } else if ($value instanceof Add) {
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
      $this->lastError = $this->logger->error("ON DUPLICATE Strategy $strategyClass is not supported yet.");
      return null;
    }
  }

  protected function fetchReturning($res, string $returningCol) {
    $this->lastInsertId = mysqli_insert_id($this->connection);
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
      return "INTEGER AUTO_INCREMENT";
    } else if ($column instanceof IntColumn) {
      $unsigned = $column->isUnsigned() ? " UNSIGNED" : "";
      return $column->getType() . $unsigned;
    } else if ($column instanceof DateTimeColumn) {
      return "DATETIME";
    } else if ($column instanceof BoolColumn) {
      return "BOOLEAN";
    } else if ($column instanceof JsonColumn) {
      return "LONGTEXT"; # some maria db setups don't allow JSON here…
    } else if ($column instanceof NumericColumn) {
      $digitsTotal = $column->getTotalDigits();
      $digitsDecimal = $column->getDecimalDigits();
      $type = $column->getTypeName();
      if ($digitsTotal !== null) {
        if ($digitsDecimal !== null) {
          return "$type($digitsTotal,$digitsDecimal)";
        } else {
          return "$type($digitsTotal)";
        }
      } else {
        return $type;
      }
    } else {
      $this->lastError = $this->logger->error("Unsupported Column Type: " . get_class($column));
      return NULL;
    }
  }

  public function getColumnDefinition(Column $column): ?string {
    $columnName = $this->columnName($column->getName());
    $defaultValue = $column->getDefaultValue();
    if ($column instanceof EnumColumn) { // check this, shouldn't it be in getColumnType?
      $values = array();
      foreach ($column->getValues() as $value) {
        $values[] = $this->getValueDefinition($value);
      }

      $values = implode(",", $values);
      $type = "ENUM($values)";
    } else {
      $type = $this->getColumnType($column);
      if (!$type) {
        return null;
      }
    }

    if ($type === "LONGTEXT") {
      $defaultValue = NULL; # must be null :(
    }

    $notNull = $column->notNull() ? " NOT NULL" : "";
    if (!is_null($defaultValue) || !$column->notNull()) {
      $defaultValue = " DEFAULT " . $this->getValueDefinition($defaultValue);
    } else {
      $defaultValue = "";
    }

    return "$columnName $type$notNull$defaultValue";
  }

  public function getValueDefinition($value) {
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
      $str = addslashes($value);
      return "'$str'";
    }
  }

  public function addValue($val, &$params = NULL, bool $unsafe = false) {
    if ($val instanceof Expression) {
      return $val->getExpression($this, $params);
    } else {
      if ($unsafe) {
        return $this->getUnsafeValue($val);
      } else {
        $params[] = $val;
        return "?";
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
        return "`$name` $alias";
      } else {
        $parts = explode(".", $table);
        return implode(".", array_map(function ($n) {
          return "`$n`";
        }, $parts));
      }
    }
  }

  public function columnName($col): string {
    if ($col instanceof Keyword) {
      return $col->getValue();
    } elseif (is_array($col)) {
      $columns = array();
      foreach ($col as $c) $columns[] = $this->columnName($c);
      return implode(",", $columns);
    } else {
      if (($index = strrpos($col, ".")) !== FALSE) {
        $tableName = $this->tableName(substr($col, 0, $index));
        $columnName = $this->columnName(substr($col, $index + 1));
        return "$tableName.$columnName";
      } else if (($index = stripos($col, " as ")) !== FALSE) {
        $columnName = $this->columnName(trim(substr($col, 0, $index)));
        $alias = trim(substr($col, $index + 4));
        return "$columnName as $alias";
      } else {
        return "`$col`";
      }
    }
  }

  public function getStatus() {
    return mysqli_stat($this->connection);
  }

  public function createTriggerBody(CreateTrigger $trigger, array $parameters = []): ?string {
    $values = array();

    foreach ($parameters as $paramValue) {
      if ($paramValue instanceof CurrentTable) {
        $values[] = $this->getUnsafeValue($trigger->getTable());
      } elseif ($paramValue instanceof CurrentColumn) {
        $prefix = ($trigger->getEvent() !== "DELETE" ? "NEW." : "OLD.");
        $values[] = $this->columnName($prefix . $paramValue->getName());
      } else {
        $values[] = $paramValue;
      }
    }

    $procName = $trigger->getProcedure()->getName();
    $procParameters = implode(",", $values);
    return "CALL $procName($procParameters)";
  }

  private function getParameterDefinition(Column $parameter, bool $out = false): string {
    $out = ($out ? "OUT" : "IN");
    $name = $parameter->getName();
    $type = $this->getColumnType($parameter);
    return "$out $name $type";
  }

  public function getProcedureHead(CreateProcedure $procedure): ?string {
    $name = $procedure->getName();
    $returns = $procedure->getReturns();
    $paramDefs = [];

    foreach ($procedure->getParameters() as $parameter) {
      if ($parameter instanceof Column) {
        $paramDefs[] = $this->getParameterDefinition($parameter);
      } else if ($parameter instanceof CurrentTable) {
        $paramDefs[] = $this->getParameterDefinition($parameter->toColumn());
      } else {
        $this->lastError = $this->logger->error("PROCEDURE parameter type " . gettype($returns) . "  is not implemented yet");
        return null;
      }
    }

    if ($returns) {
      if ($returns instanceof Column) {
        $paramDefs[] = $this->getParameterDefinition($returns, true);
      } else if (!($returns instanceof Trigger)) { // mysql does not need to return triggers here
        $this->lastError = $this->logger->error("PROCEDURE RETURN type " . gettype($returns) . "  is not implemented yet");
        return null;
      }
    }

    $paramDefs = implode(",", $paramDefs);
    return "CREATE PROCEDURE $name($paramDefs)";
  }

  protected function buildUnsafe(Query $statement): string {
    $params = [];
    $query = $statement->build($params);

    foreach ($params as $value) {
      $query = preg_replace("?", $this->getUnsafeValue($value), $query, 1);
    }

    return $query;
  }

  public function tableExists(string $tableName): bool {
    $tableSchema = $this->connectionData->getProperty("database");
    $res = $this->select(new Count())
      ->from("information_schema.TABLES")
      ->where(new Compare("TABLE_NAME", $tableName, "=", true))
      ->where(new Compare("TABLE_SCHEMA", $tableSchema, "=", true))
      ->where(new CondLike(new Column("TABLE_TYPE"), "BASE TABLE"))
      ->execute();

    return $res && $res[0]["count"] > 0;
  }

  public function listTables(): ?array {
    $tableSchema = $this->connectionData->getProperty("database");
    $res = $this->select("TABLE_NAME")
      ->from("information_schema.TABLES")
      ->where(new Compare("TABLE_SCHEMA", $tableSchema, "=", true))
      ->where(new CondLike(new Column("TABLE_TYPE"), "BASE TABLE"))
      ->execute();

    if ($res !== false) {
      $tableNames = [];

      foreach ($res as $row) {
        $tableNames[] = $row["TABLE_NAME"];
      }

      return $tableNames;
    }

    return null;
  }
}

class RowIteratorMySQL extends RowIterator {

  public function __construct($resultSet, bool $useCache = false) {
    parent::__construct($resultSet, $useCache);
  }

  protected function getNumRows(): int {
    return $this->resultSet->num_rows;
  }

  protected function fetchRow(int $index): array {
    // check if we already fetched that row
    if (!$this->useCache || $index >= count($this->fetchedRows)) {
      // if not, fetch it from the result set
      $row = $this->resultSet->fetch_assoc();
      if ($this->useCache) {
        $this->fetchedRows[] = $row;
      }

      // close result set, after everything's fetched
      if ($index >= $this->numRows - 1) {
        $this->resultSet->close();
      }
    } else {
      $row = $this->fetchedRows[$index];
    }

    return $row;
  }

  public function rewind(): void {
    if ($this->useCache) {
      $this->rowIndex = 0;
    } else if ($this->rowIndex !== 0) {
      throw new \Exception("RowIterator::rewind() not supported, when caching is disabled");
    }
  }
}