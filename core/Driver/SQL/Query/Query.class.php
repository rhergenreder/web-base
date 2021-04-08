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

  // can actually return bool|array (depending on success and query type)
  public function execute() {
    return $this->sql->executeQuery($this);
  }

  public abstract function build(array &$params): ?string;
}