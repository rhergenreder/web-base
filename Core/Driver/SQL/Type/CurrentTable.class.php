<?php

namespace Core\Driver\SQL\Type;

use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Column\StringColumn;
use Core\Driver\SQL\Expression\Expression;
use Core\Driver\SQL\MySQL;
use Core\Driver\SQL\PostgreSQL;
use Core\Driver\SQL\SQL;
use Exception;

class CurrentTable extends Expression {

  public function __construct() {
  }

  function getExpression(SQL $sql, array &$params): string {
    if ($sql instanceof MySQL) {
      return $sql->columnName("CURRENT_TABLE");
    } else if ($sql instanceof PostgreSQL) {
      return "TG_TABLE_NAME";
    } else {
      throw new Exception("CurrentTable Not implemented for driver type: " . get_class($sql));
    }
  }

  public function toColumn(): Column {
    return new StringColumn("CURRENT_TABLE");
  }
}