<?php

namespace Core\Configuration\Patch;

use Core\Configuration\DatabaseScript;
use Core\Driver\SQL\Column\IntColumn;
use Core\Driver\SQL\Condition\Compare;
use Core\Driver\SQL\SQL;
use Core\Driver\SQL\Type\CurrentColumn;
use Core\Driver\SQL\Type\CurrentTable;
use Core\Driver\SQL\Type\Trigger;

class EntityLog_2021_04_08 extends DatabaseScript {

  public static function createQueries(SQL $sql): array {

    $queries = array();

    $queries[] = $sql->createTable("EntityLog")
      ->addInt("entityId")
      ->addString("tableName")
      ->addDateTime("modified", false, $sql->now())
      ->addInt("lifetime", false, 90);

    $insertProcedure = $sql->createProcedure("InsertEntityLog")
      ->param(new CurrentTable())
      ->param(new IntColumn("id"))
      ->param(new IntColumn("lifetime", false, 90))
      ->returns(new Trigger())
      ->exec(array(
        $sql->insert("EntityLog", ["entityId", "tableName", "lifetime"])
          ->addRow(new CurrentColumn("id"), new CurrentTable(), new CurrentColumn("lifetime"))
      ));

    $updateProcedure = $sql->createProcedure("UpdateEntityLog")
      ->param(new CurrentTable())
      ->param(new IntColumn("id"))
      ->returns(new Trigger())
      ->exec(array(
        $sql->update("EntityLog")
          ->set("modified", $sql->now())
          ->where(new Compare("entityId", new CurrentColumn("id")))
          ->where(new Compare("tableName", new CurrentTable()))
      ));

    $deleteProcedure = $sql->createProcedure("DeleteEntityLog")
      ->param(new CurrentTable())
      ->param(new IntColumn("id"))
      ->returns(new Trigger())
      ->exec(array(
        $sql->delete("EntityLog")
          ->where(new Compare("entityId", new CurrentColumn("id")))
          ->where(new Compare("tableName", new CurrentTable()))
      ));

    $queries[] = $insertProcedure;
    $queries[] = $updateProcedure;
    $queries[] = $deleteProcedure;

    return $queries;
  }

}
