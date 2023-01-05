<?php

namespace Core\Driver\SQL\Expression;

use Core\Driver\SQL\MySQL;
use Core\Driver\SQL\PostgreSQL;
use Core\Driver\SQL\SQL;
use Exception;

class CurrentTimeStamp extends Expression {

  function getExpression(SQL $sql, array &$params): string {
    if ($sql instanceof MySQL) {
      return "NOW()";
    } else if ($sql instanceof PostgreSQL) {
      return "CURRENT_TIMESTAMP";
    } else {
      throw new Exception("CurrentTimeStamp Not implemented for driver type: " . get_class($sql));
    }
  }
}