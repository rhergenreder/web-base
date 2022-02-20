<?php

namespace Configuration\Patch;

use Configuration\DatabaseScript;
use Driver\SQL\Column\IntColumn;
use Driver\SQL\Condition\Compare;
use Driver\SQL\Query\CreateProcedure;
use Driver\SQL\SQL;
use Driver\SQL\Type\CurrentColumn;
use Driver\SQL\Type\CurrentTable;
use Driver\SQL\Type\Trigger;

class log extends DatabaseScript {

  public static function createTableLog(SQL $sql, string $table, int $lifetime = 90): array {
    return [
      $sql->createTrigger("${table}_trg_insert")
        ->after()->insert($table)
        ->exec(new CreateProcedure($sql, "InsertEntityLog"), [
          "tableName" => new CurrentTable(),
          "entityId" => new CurrentColumn("uid"),
          "lifetime" => $lifetime,
        ]),

      $sql->createTrigger("${table}_trg_update")
        ->after()->update($table)
        ->exec(new CreateProcedure($sql, "UpdateEntityLog"), [
          "tableName" => new CurrentTable(),
          "entityId" => new CurrentColumn("uid"),
        ]),

      $sql->createTrigger("${table}_trg_delete")
        ->after()->delete($table)
        ->exec(new CreateProcedure($sql, "DeleteEntityLog"), [
          "tableName" => new CurrentTable(),
          "entityId" => new CurrentColumn("uid"),
        ])
    ];
  }

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
      ->param(new IntColumn("lifetime", false, 90))
      ->returns(new Trigger())
      ->exec(array(
        $sql->insert("EntityLog", ["entityId", "tableName", "lifetime"])
          ->addRow(new CurrentColumn("uid"), new CurrentTable(), new CurrentColumn("lifetime"))
      ));

    $updateProcedure = $sql->createProcedure("UpdateEntityLog")
      ->param(new CurrentTable())
      ->param(new IntColumn("uid"))
      ->returns(new Trigger())
      ->exec(array(
        $sql->update("EntityLog")
          ->set("modified", $sql->now())
          ->where(new Compare("entityId", new CurrentColumn("uid")))
          ->where(new Compare("tableName", new CurrentTable()))
      ));

    $deleteProcedure = $sql->createProcedure("DeleteEntityLog")
      ->param(new CurrentTable())
      ->param(new IntColumn("uid"))
      ->returns(new Trigger())
      ->exec(array(
        $sql->delete("EntityLog")
          ->where(new Compare("entityId", new CurrentColumn("uid")))
          ->where(new Compare("tableName", new CurrentTable()))
      ));

    $queries[] = $insertProcedure;
    $queries[] = $updateProcedure;
    $queries[] = $deleteProcedure;

    $tables = ["ContactRequest"];
    foreach ($tables as $table) {
      $queries = array_merge($queries, self::createTableLog($sql, $table));
    }

    return $queries;
  }

}
