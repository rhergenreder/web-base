<?php

use Core\Configuration\CreateDatabase;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Strategy\UpdateStrategy;

$queries[] = $sql->insert("Settings", ["name", "value", "private", "readonly"])
  ->onDuplicateKeyStrategy(new UpdateStrategy(
      ["name"],
      ["name" => new Column("name")])
  )
  ->addRow("mail_contact_gpg_key_id", null, false, true)
  ->addRow("mail_contact", "''", false, false);

CreateDatabase::loadDefaultACL($sql, $queries, [
  \Core\API\Settings\ImportGPG::class,
  \Core\API\Settings\RemoveGPG::class
]);
