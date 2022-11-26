<?php

namespace Core\Driver\SQL\Query;

use Core\Driver\SQL\Condition\CondOr;
use Core\Driver\SQL\SQL;

class Delete extends ConditionalQuery {

  private string $table;

  public function __construct(SQL $sql, string $table) {
    parent::__construct($sql);
    $this->table = $table;
  }

  public function getTable(): string { return $this->table; }

  public function build(array &$params): ?string {
    $table = $this->sql->tableName($this->getTable());
    $where = $this->getWhereClause($params);
    return "DELETE FROM $table$where";
  }
}
