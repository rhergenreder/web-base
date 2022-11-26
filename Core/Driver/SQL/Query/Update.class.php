<?php

namespace Core\Driver\SQL\Query;

use Core\Driver\SQL\SQL;

class Update extends ConditionalQuery {

  private array $values;
  private string $table;

  public function __construct(SQL $sql, string $table) {
    parent::__construct($sql);
    $this->values = array();
    $this->table = $table;
  }

  public function set(string $key, $val): Update {
    $this->values[$key] = $val;
    return $this;
  }

  public function getTable(): string { return $this->table; }
  public function getValues(): array { return $this->values; }

  public function build(array &$params): ?string {
    $table = $this->sql->tableName($this->getTable());

    $valueStr = array();
    foreach($this->getValues() as $key => $val) {
      $valueStr[] = $this->sql->columnName($key) . "=" . $this->sql->addValue($val, $params);
    }
    $valueStr = implode(",", $valueStr);

    $where = $this->getWhereClause($params);
    return "UPDATE $table SET $valueStr$where";
  }
}