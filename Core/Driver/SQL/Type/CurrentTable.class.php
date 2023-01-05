<?php

namespace Core\Driver\SQL\Type;

use Core\Driver\SQL\Expression\Expression;
use Core\Driver\SQL\MySQL;
use Core\Driver\SQL\PostgreSQL;
use Core\Driver\SQL\SQL;

class CurrentTable extends Expression {

  public function __construct() {
  }

  function getExpression(SQL $sql, array &$params): string {
    if ($sql instanceof MySQL) {
      // CURRENT_TABLE
    } else if ($sql instanceof PostgreSQL) {
      return "TG_TABLE_NAME";
    } else {

    }
  }
}