<?php

namespace Driver\SQL\Query;

use Driver\SQL\Condition\CondOr;
use Driver\SQL\Join;
use Driver\SQL\SQL;

class Select extends Query {

  private array $columns;
  private array $tables;
  private array $conditions;
  private array $joins;
  private array $orderColumns;
  private array $groupColumns;
  private bool $sortAscending;
  private int $limit;
  private int $offset;

  public function __construct($sql, ...$columns) {
    parent::__construct($sql);
    $this->columns = (!empty($columns) && is_array($columns[0])) ? $columns[0] : $columns;
    $this->tables = array();
    $this->conditions = array();
    $this->joins = array();
    $this->orderColumns = array();
    $this->groupColumns = array();
    $this->limit = 0;
    $this->offset = 0;
    $this->sortAscending = true;
  }

  public function from(...$tables): Select {
    $this->tables = array_merge($this->tables, $tables);
    return $this;
  }

  public function where(...$conditions): Select {
    $this->conditions[] = (count($conditions) === 1 ? $conditions : new CondOr($conditions));
    return $this;
  }

  public function innerJoin(string $table, string $columnA, string $columnB, ?string $tableAlias = null): Select {
    $this->joins[] = new Join("INNER", $table, $columnA, $columnB, $tableAlias);
    return $this;
  }

  public function leftJoin(string $table, string $columnA, string $columnB, ?string $tableAlias = null): Select {
    $this->joins[] = new Join("LEFT", $table, $columnA, $columnB, $tableAlias);
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

  public function execute() {
    return $this->sql->executeQuery($this, true);
  }

  public function getColumns(): array { return $this->columns; }
  public function getTables(): array { return $this->tables; }
  public function getConditions(): array { return $this->conditions; }
  public function getJoins(): array { return $this->joins; }
  public function isOrderedAscending(): bool { return $this->sortAscending; }
  public function getOrderBy(): array { return $this->orderColumns; }
  public function getLimit(): int { return $this->limit; }
  public function getOffset(): int { return $this->offset; }
  public function getGroupBy(): array { return $this->groupColumns; }

  public function build(array &$params): ?string {
    $columns = $this->sql->columnName($this->getColumns());
    $tables = $this->getTables();

    if (!$tables) {
      return "SELECT $columns";
    }

    $tables = $this->sql->tableName($tables);
    $where = $this->sql->getWhereClause($this->getConditions(), $params);

    $joinStr = "";
    $joins = $this->getJoins();
    if (!empty($joins)) {
      foreach ($joins as $join) {
        $type = $join->getType();
        $joinTable = $this->sql->tableName($join->getTable());
        $columnA = $this->sql->columnName($join->getColumnA());
        $columnB = $this->sql->columnName($join->getColumnB());
        $tableAlias = ($join->getTableAlias() ? " " . $join->getTableAlias() : "");

        $joinStr .= " $type JOIN $joinTable$tableAlias ON $columnA=$columnB";
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
    return "SELECT $columns FROM $tables$joinStr$where$groupBy$orderBy$limit$offset";
  }
}