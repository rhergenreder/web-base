<?php

namespace Driver\SQL\Query;

use Driver\SQL\SQL;

abstract class Query {

  protected SQL $sql;
  public bool $dump;

  public function __construct($sql) {
    $this->sql = $sql;
    $this->dump = false;
  }

  public function dump() {
    $this->dump = true;
    return $this;
  }

  public abstract function execute();

}