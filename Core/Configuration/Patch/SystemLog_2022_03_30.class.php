<?php

namespace Core\Configuration\Patch;

use Core\Configuration\DatabaseScript;
use Core\Driver\SQL\SQL;

class SystemLog_2022_03_30 extends DatabaseScript {

  public static function createQueries(SQL $sql): array {
    return [
      $sql->insert("ApiPermission", ["method", "groups", "description"])
        ->addRow("Logs/get", [USER_GROUP_ADMIN], "Allows users to fetch system logs")
    ];
  }
}