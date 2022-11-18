<?php

namespace Core\Driver\SQL\Query;

use Core\Driver\SQL\SQL;

class Commit extends Query {
  public function __construct(SQL $sql) {
    parent::__construct($sql);
  }

  public function build(array &$params): ?string {
    return "COMMIT";
  }
}