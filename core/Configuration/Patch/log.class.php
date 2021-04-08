<?php

namespace Configuration\Patch;

use Configuration\DatabaseScript;
use Driver\SQL\Column\IntColumn;
use Driver\SQL\Condition\Compare;
use Driver\SQL\SQL;
use Driver\SQL\Type\CurrentColumn;
use Driver\SQL\Type\CurrentTable;
use Driver\SQL\Type\Trigger;

class log extends DatabaseScript {

  public static function createQueries(SQL $sql): array {

    $queries = array();

    $queries[] = $sql->createTable("EntityLog")
      ->addInt("entityId")
      ->addString("tableName")
      ->addDateTime("modified", false, $sql->now())
      ->addInt("lifetime", false, 90);

    $insertProcedure = $sql->createProcedure("InsertEntityLog")
      ->param(new CurrentTable())
      ->param(new IntColumn("uid"))
      ->returns(new Trigger())
      ->exec(array(
        $sql->insert("EntityLog", ["entityId", "tableName"])
          ->addRow(new CurrentColumn("uid"), new CurrentTable())
      ));

    $updateProcedure = $sql->createProcedure("UpdateEntityLog")
      ->param(new CurrentTable())
      ->param(new IntColumn("uid"))
      ->returns(new Trigger())
      ->exec(array(
        $sql->update("EntityLog")
          ->set("modified", $sql->now())
          ->where(new Compare("entityId",new CurrentColumn("uid")))
          ->where(new Compare("tableName",new CurrentTable()))
      ));

    $queries[] = $insertProcedure;
    $queries[] = $updateProcedure;

    $tables = ["ContactRequest"];
    foreach ($tables as $table) {

      $queries[] = $sql->createTrigger("${table}_trg_insert")
        ->after()->insert($table)
        ->exec($insertProcedure);

      $queries[] = $sql->createTrigger("${table}_trg_update")
        ->after()->update($table)
        ->exec($updateProcedure);
    }

    return $queries;
  }

}
