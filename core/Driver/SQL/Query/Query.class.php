<?php

namespace Driver\SQL\Query;

use Driver\SQL\SQL;

abstract class Query {

  protected SQL $sql;

  public function __construct($sql) {
    $this->sql = $sql;
  }

  public abstract function execute();

}