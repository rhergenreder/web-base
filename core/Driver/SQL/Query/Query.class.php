<?php

namespace Driver\SQL\Query;

abstract class Query {

  protected $sql;

  public function __construct($sql) {
    $this->sql = $sql;
  }

  public abstract function execute();

};

?>
