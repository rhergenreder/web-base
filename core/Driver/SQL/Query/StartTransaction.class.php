<?php

namespace Driver\SQL\Query;

use Driver\SQL\SQL;

class StartTransaction extends Query {
  public function __construct(SQL $sql) {
    parent::__construct($sql);
  }

  public function build(array &$params): ?string {
    return "START TRANSACTION";
  }
}