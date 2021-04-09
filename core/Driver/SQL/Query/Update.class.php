<?php

namespace Driver\SQL\Query;

use Driver\SQL\Condition\CondOr;
use Driver\SQL\SQL;

class Update extends Query {

  private array $values;
  private string $table;
  private array $conditions;

  public function __construct(SQL $sql, string $table) {
    parent::__construct($sql);
    $this->values = array();
    $this->table = $table;
    $this->conditions = array();
  }

  public function where(...$conditions): Update {
    $this->conditions[] = (count($conditions) === 1 ? $conditions : new CondOr($conditions));
    return $this;
  }

  public function set(string $key, $val): Update {
    $this->values[$key] = $val;
    return $this;
  }

  public function getTable(): string { return $this->table; }
  public function getConditions(): array { return $this->conditions; }
  public function getValues(): array { return $this->values; }

  public function build(array &$params): ?string {
    $table = $this->sql->tableName($this->getTable());

    $valueStr = array();
    foreach($this->getValues() as $key => $val) {
      $valueStr[] = $this->sql->columnName($key) . "=" . $this->sql->addValue($val, $params);
    }
    $valueStr = implode(",", $valueStr);

    $where = $this->sql->getWhereClause($this->getConditions(), $params);
    return "UPDATE $table SET $valueStr$where";
  }
}