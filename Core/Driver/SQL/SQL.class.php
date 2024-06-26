<?php

namespace Core\Driver\SQL;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Condition\Condition;
use Core\Driver\SQL\Constraint\Constraint;
use Core\Driver\SQL\Constraint\Unique;
use Core\Driver\SQL\Constraint\PrimaryKey;
use Core\Driver\SQL\Constraint\ForeignKey;
use Core\Driver\SQL\Expression\CurrentTimeStamp;
use Core\Driver\SQL\Query\AlterTable;
use Core\Driver\SQL\Query\Commit;
use Core\Driver\SQL\Query\CreateProcedure;
use Core\Driver\SQL\Query\CreateTable;
use Core\Driver\SQL\Query\CreateTrigger;
use Core\Driver\SQL\Query\Delete;
use Core\Driver\SQL\Query\Drop;
use Core\Driver\SQL\Query\Insert;
use Core\Driver\SQL\Query\Query;
use Core\Driver\SQL\Query\RollBack;
use Core\Driver\SQL\Query\Select;
use Core\Driver\SQL\Query\StartTransaction;
use Core\Driver\SQL\Query\Truncate;
use Core\Driver\SQL\Query\Update;
use Core\Driver\SQL\Strategy\CascadeStrategy;
use Core\Driver\SQL\Strategy\SetDefaultStrategy;
use Core\Driver\SQL\Strategy\SetNullStrategy;
use Core\Driver\SQL\Strategy\Strategy;
use Core\Objects\ConnectionData;

abstract class SQL {

  const FETCH_NONE = 0;
  const FETCH_ONE = 1;
  const FETCH_ALL = 2;
  const FETCH_ITERATIVE = 3;

  protected Logger $logger;
  protected string $lastError;
  protected $connection;
  protected ConnectionData $connectionData;
  protected int $lastInsertId;

  protected bool $logQueries;

  public function __construct($connectionData) {
    $this->connection = NULL;
    $this->lastError = 'Unknown Error';
    $this->connectionData = $connectionData;
    $this->lastInsertId = 0;
    $this->logger = new Logger(getClassName($this), $this);
    $this->logQueries = false;
  }

  public function isConnected(): bool {
    return !is_null($this->connection) && !is_bool($this->connection);
  }

  public function getLastError(): string {
    return trim($this->lastError);
  }

  public function createTable($tableName): CreateTable {
    return new CreateTable($this, $tableName);
  }

  public function insert($tableName, $columns=array()): Insert {
    return new Insert($this, $tableName, $columns);
  }

  public function select(...$columNames): Select {
    return new Select($this, $columNames);
  }

  public function truncate($table): Truncate {
    return new Truncate($this, $table);
  }

  public function delete($table): Delete {
    return new Delete($this, $table);
  }

  public function update($table): Update {
    return new Update($this, $table);
  }

  public function drop(string $table): Drop {
    return new Drop($this, $table);
  }

  public function startTransaction(): bool {
    return (new StartTransaction($this))->execute();
  }

  public function commit(): bool {
    return (new Commit($this))->execute();
  }

  public function rollback(): bool {
    return (new RollBack($this))->execute();
  }

  public function alterTable($tableName): AlterTable {
    return new AlterTable($this, $tableName);
  }

  public function createTrigger($triggerName): CreateTrigger {
    return new CreateTrigger($this, $triggerName);
  }

  public function createProcedure(string $procName): CreateProcedure {
    return new CreateProcedure($this, $procName);
  }


  // ####################
  // ### ABSTRACT METHODS
  // ####################

  // Misc
  public abstract function checkRequirements();
  public abstract function getDriverName();

  // Connection Management
  public abstract function connect();
  public abstract function disconnect();

  // Schema
  public abstract function tableExists(string $tableName): bool;

  public abstract function listTables(): ?array;

  /**
   * @param Query $query
   * @param int $fetchType
   * @return mixed
   */
  public function executeQuery(Query $query, int $fetchType = self::FETCH_NONE) {

    $parameters = [];
    $queryStr = $query->build($parameters);

    if ($query->dump) {
      var_dump($queryStr);
      var_dump($parameters);
    }

    if ($queryStr === null) {
      return false;
    }

    $logLevel = Logger::LOG_LEVEL_ERROR;
    // $logLevel = Logger::LOG_LEVEL_DEBUG;
    if ($query instanceof Insert && $query->getTableName() === "SystemLog") {
      $logLevel = Logger::LOG_LEVEL_NONE;
    }

    $res = $this->execute($queryStr, $parameters, $fetchType, $logLevel);
    $success = ($res !== FALSE);

    // fetch generated serial ids for Insert statements
    $generatedColumn = ($query instanceof Insert ? $query->getReturning() : null);
    if ($success && $generatedColumn) {
      $this->fetchReturning($res, $generatedColumn);
    }

    if ($this->logQueries && (!($query instanceof Insert) || $query->getTableName() !== "SystemLog")) {

      if ($success === false || $fetchType == self::FETCH_NONE) {
        $result = var_export($success, true);
      } else if ($fetchType === self::FETCH_ALL) {
        $result = count($res) . " rows";
      } else if ($fetchType === self::FETCH_ONE) {
        $result = ($res === null ? "(empty)" : "1 row");
      } else if ($fetchType === self::FETCH_ITERATIVE) {
        $result = $res->getNumRows() .  " rows (iterative)";
      } else {
        $result = "Unknown";
      }

      $message = sprintf("Query: %s, Parameters: %s, Result: %s",
        var_export($queryStr, true), var_export($parameters, true), $result
      );

      if ($success === false) {
        $message .= "Error: " . var_export($this->lastError, true);
      }

      $this->logger->debug($message);
    }

    return $fetchType === self::FETCH_NONE ? $success : $res;
  }

  public function getWhereClause($conditions, &$params): string {
    if (!$conditions) {
      return "";
    } else {
      return " WHERE " . $this->buildCondition($conditions, $params);
    }
  }

  public function getConstraintDefinition(Constraint $constraint): ?string {
    $columnName = $this->columnName($constraint->getColumnNames());

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
      $this->lastError = $this->logger->error("Unsupported constraint type: " . get_class($constraint));
      return null;
    }
  }

  protected abstract function fetchReturning($res, string $returningCol);
  public abstract function getColumnDefinition(Column $column): ?string;
  public abstract function getOnDuplicateStrategy(?Strategy $strategy, &$params): ?string;
  public abstract function createTriggerBody(CreateTrigger $trigger, array $params = []): ?string;
  public abstract function getProcedureHead(CreateProcedure $procedure): ?string;
  public abstract function getColumnType(Column $column): ?string;
  public abstract function getStatus();
  public function getProcedureTail(): string { return ""; }
  public function getReturning(?string $columns): string { return ""; }

  public function getProcedureBody(CreateProcedure $procedure): string {
    $statements = "";
    foreach ($procedure->getStatements() as $statement) {
      $statements .= $this->buildUnsafe($statement) . ";";
    }
    return $statements;
  }

  public function getLogger(): Logger {
    return $this->logger;
  }

  protected function getUnsafeValue($value): ?string {
    if (is_string($value)) {
      return "'" . addslashes("$value") . "'"; // unsafe operation here...
    } else if (is_numeric($value) || is_bool($value)) {
      return $value;
    } else if ($value instanceof Column) {
      return $this->columnName($value);
    } else if ($value === null) {
      return "NULL";
    } else {
      $this->lastError = $this->logger->error("Cannot create unsafe value of type: " . gettype($value));
      return null;
    }
  }

  protected abstract function getValueDefinition($val);
  public abstract function addValue($val, &$params = NULL, bool $unsafe = false);
  protected abstract function buildUnsafe(Query $statement): string;

  public abstract function tableName($table): string;
  public abstract function columnName($col): string;

  // Special Keywords and functions
  public function now(): CurrentTimeStamp { return new CurrentTimeStamp(); }
  public function currentTimestamp(): CurrentTimeStamp { return new CurrentTimeStamp(); }

  // Statements
  /**
   * @return mixed
   */
  protected abstract function execute($query, $values = NULL, int $fetchType = self::FETCH_NONE, int $logLevel = Logger::LOG_LEVEL_ERROR);

  public function buildCondition(Condition|array $condition, &$params): string {

    if (is_array($condition)) {
      if (count($condition) === 1) {
        return $this->buildCondition($condition[0], $params);
      } else {
        $conditions = array();
        foreach ($condition as $cond) {
          $conditions[] = $this->buildCondition($cond, $params);
        }
        return implode(" AND ", $conditions);
      }
    } else {
      return $this->addValue($condition, $params);
    }
  }

  public function setLastError($str): void {
    $this->lastError = $str;
  }

  public function getLastInsertId(): int {
    return $this->lastInsertId;
  }

  public function close(): void {
    $this->disconnect();
    $this->connection = NULL;
  }

  public static function createConnection(ConnectionData $connectionData) {
    $type = $connectionData->getProperty("type");
    if ($type === "mysql") {
      $sql = new MySQL($connectionData);
    } else if ($type === "postgres") {
      $sql = new PostgreSQL($connectionData);
    } else {
      Logger::instance()->error("Unknown database type: $type");
      return "Unknown database type";
    }

    if ($sql->checkRequirements()) {
      $sql->connect();
    }

    return $sql;
  }

  public function parseBool($val) : bool {
    return in_array($val, array(true, 1, '1', 't', 'true', 'TRUE'), true);
  }
}