<?php

namespace Driver\SQL;

use Driver\SQL\Column\Column;
use Driver\SQL\Condition\Compare;
use Driver\SQL\Condition\CondAnd;
use Driver\SQL\Condition\CondBool;
use Driver\SQL\Condition\CondIn;
use Driver\SQL\Condition\Condition;
use Driver\SQL\Condition\CondKeyword;
use Driver\SQL\Condition\CondNot;
use Driver\Sql\Condition\CondNull;
use Driver\SQL\Condition\CondOr;
use Driver\SQL\Condition\Exists;
use Driver\SQL\Constraint\Constraint;
use Driver\SQL\Constraint\Unique;
use Driver\SQL\Constraint\PrimaryKey;
use Driver\SQL\Constraint\ForeignKey;
use Driver\SQL\Expression\CaseWhen;
use Driver\SQL\Expression\CurrentTimeStamp;
use Driver\SQL\Expression\Expression;
use Driver\SQL\Expression\Sum;
use Driver\SQL\Query\AlterTable;
use Driver\SQL\Query\CreateProcedure;
use Driver\SQL\Query\CreateTable;
use Driver\SQL\Query\CreateTrigger;
use Driver\SQL\Query\Delete;
use Driver\SQL\Query\Drop;
use Driver\SQL\Query\Insert;
use Driver\SQL\Query\Query;
use Driver\SQL\Query\Select;
use Driver\SQL\Query\Truncate;
use Driver\SQL\Query\Update;
use Driver\SQL\Strategy\CascadeStrategy;
use Driver\SQL\Strategy\SetDefaultStrategy;
use Driver\SQL\Strategy\SetNullStrategy;
use Driver\SQL\Strategy\Strategy;
use Objects\ConnectionData;

abstract class SQL {

  protected string $lastError;
  protected $connection;
  protected ConnectionData $connectionData;
  protected int $lastInsertId;

  public function __construct($connectionData) {
    $this->connection = NULL;
    $this->lastError = 'Unknown Error';
    $this->connectionData = $connectionData;
    $this->lastInsertId = 0;
  }

  public function isConnected(): bool {
    return !is_null($this->connection);
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

  public function executeQuery(Query $query, bool $fetchResult = false) {

    $parameters = [];
    $queryStr = $query->build($parameters);

    if($query->dump) {
      var_dump($queryStr);
      var_dump($parameters);
    }

    if ($queryStr === null) {
      return false;
    }

    $res = $this->execute($queryStr, $parameters, $fetchResult);
    $success = ($res !== FALSE);

    // fetch generated serial ids for Insert statements
    $generatedColumn = ($query instanceof Insert ? $query->getReturning() : null);
    if($success && $generatedColumn) {
      $this->fetchReturning($res, $generatedColumn);
    }

    return $fetchResult ? $res : $success;
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
      $this->lastError = "Unsupported constraint type: " . get_class($constraint);
      return null;
    }
  }

  protected abstract function fetchReturning($res, string $returningCol);
  public abstract function getColumnDefinition(Column $column): ?string;
  public abstract function getOnDuplicateStrategy(?Strategy $strategy, &$params): ?string;
  public abstract function createTriggerBody(CreateTrigger $trigger, array $params = []): ?string;
  public abstract function getProcedureHead(CreateProcedure $procedure): ?string;
  public abstract function getColumnType(Column $column): ?string;
  public function getProcedureTail(): string { return ""; }
  public function getReturning(?string $columns): string { return ""; }

  public function getProcedureBody(CreateProcedure $procedure): string {
    $statements = "";
    foreach ($procedure->getStatements() as $statement) {
      $statements .= $this->buildUnsafe($statement) . ";";
    }
    return $statements;
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
      $this->lastError = "Cannot create unsafe value of type: " . gettype($value);
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

  public function count($col = NULL): Keyword {
    if (is_null($col)) {
      return new Keyword("COUNT(*) AS count");
    } else if($col instanceof Keyword) {
      return new Keyword("COUNT(" . $col->getValue() . ") AS count");
    } else {
      $countCol = strtolower(str_replace(".","_", $col)) .  "_count";
      $col = $this->columnName($col);
      return new Keyword("COUNT($col) AS $countCol");
    }
  }

  public function distinct($col): Keyword {
    $col = $this->columnName($col);
    return new Keyword("DISTINCT($col)");
  }

  // Statements
  protected abstract function execute($query, $values=NULL, $returnValues=false);

  public function buildCondition($condition, &$params) {

    if ($condition instanceof CondOr) {
      $conditions = array();
      foreach($condition->getConditions() as $cond) {
        $conditions[] = $this->buildCondition($cond, $params);
      }
      return "(" . implode(" OR ", $conditions) . ")";
    } else if ($condition instanceof CondAnd) {
      $conditions = array();
      foreach($condition->getConditions() as $cond) {
        $conditions[] = $this->buildCondition($cond, $params);
      }
      return "(" . implode(" AND ", $conditions) . ")";
    } else if ($condition instanceof Compare) {
      $column = $this->columnName($condition->getColumn());
      $value = $condition->getValue();
      $operator = $condition->getOperator();

      if ($value === null) {
        if ($operator === "=") {
          return "$column IS NULL";
        } else if ($operator === "!=") {
          return "$column IS NOT NULL";
        }
      }

      return $column . $operator . $this->addValue($value, $params);
    } else if ($condition instanceof CondBool) {
      return $this->columnName($condition->getValue());
    } else if (is_array($condition)) {
      if (count($condition) === 1) {
        return $this->buildCondition($condition[0], $params);
      } else {
        $conditions = array();
        foreach ($condition as $cond) {
          $conditions[] = $this->buildCondition($cond, $params);
        }
        return implode(" AND ", $conditions);
      }
    } else if($condition instanceof CondIn) {

      $needle = $condition->getNeedle();
      $haystack = $condition->getHaystack();
      if (is_array($haystack)) {
        $values = array();
        foreach ($haystack as $value) {
          $values[] = $this->addValue($value, $params);
        }

        $values = implode(",", $values);
      } else if($haystack instanceof Select) {
        $values = $haystack->build($params);
      } else {
        $this->lastError = "Unsupported in-expression value: " . get_class($condition);
        return false;
      }

      if ($needle instanceof Column) {
        $lhs = $this->createExpression($needle, $params);
      } else {
        $lhs = $this->addValue($needle, $params);
      }

      return "$lhs IN ($values)";
    } else if($condition instanceof CondKeyword) {
      $left = $condition->getLeftExp();
      $right = $condition->getRightExp();
      $keyword = $condition->getKeyword();
      $left = ($left instanceof Column) ? $this->columnName($left->getName()) : $this->addValue($left, $params);
      $right = ($right instanceof Column) ? $this->columnName($right->getName()) : $this->addValue($right, $params);
      return "$left $keyword $right ";
    } else if($condition instanceof CondNot) {
      $expression = $condition->getExpression();
      if ($expression instanceof Condition) {
        $expression = $this->buildCondition($expression, $params);
      } else {
        $expression = $this->columnName($expression);
      }

      return "NOT $expression";
    } else if ($condition instanceof CondNull) {
        return $this->columnName($condition->getColumn()) . " IS NULL";
    } else if ($condition instanceof Exists) {
        return "EXISTS(" .$condition->getSubQuery()->build($params) . ")";
    } else {
      $this->lastError = "Unsupported condition type: " . gettype($condition);
      return null;
    }
  }

  protected function createExpression(Expression $exp, array &$params): ?string {
    if ($exp instanceof Column) {
      return $this->columnName($exp->getName());
    } else if ($exp instanceof Query) {
      return "(" . $exp->build($params) . ")";
    } else if ($exp instanceof CaseWhen) {
      $condition = $this->buildCondition($exp->getCondition(), $params);

      // psql requires constant values here
      $trueCase = $this->addValue($exp->getTrueCase(), $params, true);
      $falseCase = $this->addValue($exp->getFalseCase(), $params, true);

      return "CASE WHEN $condition THEN $trueCase ELSE $falseCase END";
    } else if ($exp instanceof Sum) {
      $value = $this->addValue($exp->getValue(), $params);
      $alias = $this->columnName($exp->getAlias());
      return "SUM($value) AS $alias";
    } else {
      $this->lastError = "Unsupported expression type: " . get_class($exp);
      return null;
    }
  }

  public function setLastError($str) {
    $this->lastError = $str;
  }

  public function getLastInsertId(): int {
    return $this->lastInsertId;
  }

  public function close() {
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
      return "Unknown database type";
    }

    if ($sql->checkRequirements()) {
      $sql->connect();
    }

    return $sql;
  }

  public abstract function getStatus();

  public function parseBool($val) : bool {
    return in_array($val, array(true, 1, '1', 't', 'true', 'TRUE'), true);
  }
}