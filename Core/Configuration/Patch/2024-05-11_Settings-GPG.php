<?php

use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Strategy\UpdateStrategy;
use Core\Objects\DatabaseEntity\Group;

$queries[] = $sql->insert("Settings", ["name", "value", "private", "readonly"])
  ->onDuplicateKeyStrategy(new UpdateStrategy(
      ["name"],
      ["name" => new Column("name")])
  )
  ->addRow("mail_contact_gpg_key_id", null, false, true)
  ->addRow("mail_contact", "''", false, false);

$queries[] = $sql->insert("ApiPermission", ["method", "groups", "description", "is_core"])
  ->onDuplicateKeyStrategy(new UpdateStrategy(
      ["method"],
      ["method" => new Column("method")])
  )
  ->addRow("settings/importGPG",
    json_encode(\Core\API\Settings\ImportGPG::getDefaultPermittedGroups()),
    \Core\API\Settings\ImportGPG::getDescription(), true)
  ->addRow("settings/removeGPG",
    json_encode(\Core\API\Settings\RemoveGPG::getDefaultPermittedGroups()),
    \Core\API\Settings\RemoveGPG::getDescription(), true);
