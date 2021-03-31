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
  public function __construct(\Driver\SQL\SQL $sql, string $table) {
    parent::__construct($sql);
    $this->table = $table;
  }

  public function execute() {
    $this->sql->executeDrop($this);
  }

  public function getTable() {
    return $this->table;
  }
}