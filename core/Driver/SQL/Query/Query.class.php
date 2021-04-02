<?php

namespace Driver\SQL\Query;

use Driver\SQL\SQL;

abstract class Query {

  protected SQL $sql;
  public bool $dump;

  public function __construct(SQL $sql) {
    $this->sql = $sql;
    $this->dump = false;
  }

  public function dump(): Query {
    $this->dump = true;
    return $this;
  }

  public abstract function execute(): bool;

}