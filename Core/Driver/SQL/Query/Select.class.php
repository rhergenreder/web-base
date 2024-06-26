<?php

namespace Core\Driver\SQL\Query;

use Core\Driver\SQL\Condition\CondOr;
use Core\Driver\SQL\Expression\Expression;
use Core\Driver\SQL\Expression\JsonArrayAgg;
use Core\Driver\SQL\Join\InnerJoin;
use Core\Driver\SQL\Join\Join;
use Core\Driver\SQL\Join\LeftJoin;
use Core\Driver\SQL\SQL;

class Select extends ConditionalQuery {

  private array $selectValues;
  private array $tables;
  private array $joins;
  private array $orderColumns;
  private array $groupColumns;
  private array $havings;
  private bool $sortAscending;
  private int $limit;
  private int $offset;
  private bool $forUpdate;
  private int $fetchType;

  public function __construct($sql, ...$selectValues) {
    parent::__construct($sql);
    $this->selectValues = (!empty($selectValues) && is_array($selectValues[0])) ? $selectValues[0] : $selectValues;
    $this->tables = array();
    $this->havings = array();
    $this->joins = array();
    $this->orderColumns = array();
    $this->groupColumns = array();
    $this->limit = 0;
    $this->offset = 0;
    $this->sortAscending = true;
    $this->forUpdate = false;
    $this->fetchType = SQL::FETCH_ALL;
  }

  public function select(...$selectValues): Select {
    $this->selectValues = (!empty($selectValues) && is_array($selectValues[0])) ? $selectValues[0] : $selectValues;
    return $this;
  }

  public function addSelectValue(...$selectValues): Select {
    $this->selectValues = array_merge($this->selectValues, $selectValues);
    return $this;
  }

  public function from(...$tables): Select {
    $this->tables = array_merge($this->tables, $tables);
    return $this;
  }

  public function addValue($value): Select {
    $this->selectValues[] = $value;
    return $this;
  }

  public function having(...$conditions): Select {
    $this->havings[] = (count($conditions) === 1 ? $conditions : new CondOr($conditions));
    return $this;
  }

  public function innerJoin(string $table, string $columnA, string $columnB, ?string $tableAlias = null, array $conditions = []): Select {
    $this->joins[] = new InnerJoin($table, $columnA, $columnB, $tableAlias, $conditions);
    return $this;
  }

  public function leftJoin(string $table, string $columnA, string $columnB, ?string $tableAlias = null, array $conditions = []): Select {
    $this->joins[] = new LeftJoin($table, $columnA, $columnB, $tableAlias, $conditions);
    return $this;
  }

  public function addJoin(Join $join): Select {
    $this->joins[] = $join;
    return $this;
  }

  public function groupBy(...$columns): Select {
    $this->groupColumns = $columns;
    return $this;
  }

  public function orderBy(...$columns): Select {
    $this->orderColumns = $columns;
    return $this;
  }

  public function ascending(): Select {
    $this->sortAscending = true;
    return $this;
  }

  public function descending(): Select {
    $this->sortAscending = false;
    return $this;
  }

  public function limit(int $limit): Select {
    $this->limit = $limit;
    return $this;
  }

  public function offset(int $offset): Select {
    $this->offset = $offset;
    return $this;
  }

  public function lockForUpdate(): Select {
    $this->forUpdate = true;
    return $this;
  }

  public function iterator(): Select {
    $this->fetchType = SQL::FETCH_ITERATIVE;
    return $this;
  }

  public function first(): Select {
    $this->fetchType = SQL::FETCH_ONE;
    $this->limit = 1;
    return $this;
  }

  /**
   * @return mixed
   */
  public function execute() {
    return $this->sql->executeQuery($this, $this->fetchType);
  }

  public function getSelectValues(): array { return $this->selectValues; }
  public function getTables(): array { return $this->tables; }
  public function getJoins(): array { return $this->joins; }
  public function isOrderedAscending(): bool { return $this->sortAscending; }
  public function getOrderBy(): array { return $this->orderColumns; }
  public function getLimit(): int { return $this->limit; }
  public function getOffset(): int { return $this->offset; }
  public function getGroupBy(): array { return $this->groupColumns; }
  public function getHavings(): array { return $this->havings; }

  public function build(array &$params): ?string {

    $selectValues = [];
    foreach ($this->selectValues as $value) {
      if (is_string($value)) {
        $selectValues[] = $this->sql->columnName($value);
      } else {
        $selectValues[] = $this->sql->addValue($value, $params);
      }
    }

    $tables = $this->getTables();
    $selectValues = implode(",", $selectValues);

    if (!$tables) {
      return "SELECT $selectValues";
    }

    $tables = $this->sql->tableName($tables);
    $where = $this->getWhereClause($params);
    $havingClause = "";
    if (count($this->havings) > 0) {
      $havingClause  = " HAVING " . $this->sql->buildCondition($this->getHavings(), $params);
    }

    $joinStr = "";
    $joins = $this->getJoins();
    if (!empty($joins)) {
      foreach ($joins as $join) {
        $type = $join->getType();
        $joinTable = $this->sql->tableName($join->getTable());
        $tableAlias = ($join->getTableAlias() ? " " . $join->getTableAlias() : "");
        $condition = $this->sql->buildCondition($join->getConditions(), $params);
        $joinStr .= " $type JOIN $joinTable$tableAlias ON ($condition)";
      }
    }

    $groupBy = "";
    $groupColumns = $this->getGroupBy();
    if (!empty($groupColumns)) {
      $groupBy = " GROUP BY " . $this->sql->columnName($groupColumns);
    }

    $orderBy = "";
    $orderColumns = $this->getOrderBy();
    if (!empty($orderColumns)) {
      $orderBy = " ORDER BY " . $this->sql->columnName($orderColumns);
      $orderBy .= ($this->isOrderedAscending() ? " ASC" : " DESC");
    }

    $limit = ($this->getLimit() > 0 ? (" LIMIT " . $this->getLimit()) : "");
    $offset = ($this->getOffset() > 0 ? (" OFFSET " . $this->getOffset()) : "");
    $forUpdate = ($this->forUpdate ? " FOR UPDATE" : "");
    return "SELECT $selectValues FROM $tables$joinStr$where$groupBy$havingClause$orderBy$limit$offset$forUpdate";
  }
}