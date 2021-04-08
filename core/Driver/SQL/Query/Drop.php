<?php


namespace Driver\SQL\Query;

use Driver\SQL\SQL;

class Drop extends Query {

  private string $table;

  /**
   * Drop constructor.
   * @param SQL $sql
   * @param string $table
   */
  public function __construct(SQL $sql, string $table) {
    parent::__construct($sql);
    $this->table = $table;
  }

  public function getTable(): string {
    return $this->table;
  }

  public function build(array &$params, Query $context = NULL): ?string {
    return "DROP TABLE " . $this->sql->tableName($this->getTable());
  }
}