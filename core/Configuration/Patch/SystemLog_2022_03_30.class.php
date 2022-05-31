<?php

namespace Configuration\Patch;

use Configuration\DatabaseScript;
use Driver\SQL\SQL;

class SystemLog_2022_03_30 extends DatabaseScript {

  public static function createQueries(SQL $sql): array {
    return [
      $sql->createTable("SystemLog")
        ->onlyIfNotExists()
        ->addSerial("id")
        ->addDateTime("timestamp", false, $sql->now())
        ->addString("message")
        ->addString("module", 64, false, "global")
        ->addEnum("severity", ["debug", "info", "warning", "error", "severe"])
        ->primaryKey("id"),
      $sql->insert("ApiPermission", ["method", "groups", "description"])
        ->addRow("Logs/get", [USER_GROUP_ADMIN], "Allows users to fetch system logs")
    ];
  }
}