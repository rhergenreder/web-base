<?php

namespace Configuration;

use \Driver\SQL\Query\CreateTable;
use \Driver\SQL\Query\Insert;
use \Driver\SQL\Column\Column;
use \Driver\SQL\Strategy\UpdateStrategy;
use \Driver\SQL\Strategy\SetNullStrategy;
use \Driver\SQL\Strategy\CascadeStrategy;

class CreateDatabase {

  public static function createQueries($sql) {
    $queries = array();

    // Language
    $queries[] = $sql->createTable("Language")
      ->addSerial("uid")
      ->addString("code", 5)
      ->addString("name", 32)
      ->primaryKey("uid")
      ->unique("code")
      ->unique("name");

    $queries[] = $sql->insert("Language", array("uid", "code", "name"))
      ->addRow(1, "en_US", 'American English')
      ->addRow(2, "de_DE", 'Deutsch Standard')
      ->onDuplicateKeyStrategy(new UpdateStrategy(array("name" => new Column("name"))));

    $queries[] = $sql->createTable("User")
      ->addSerial("uid")
      ->addString("email", 64, true)
      ->addString("name", 32)
      ->addString("salt", 16)
      ->addString("password", 64)
      ->addInt("language_id", true, 1)
      ->primaryKey("uid")
      ->unique("email")
      ->unique("name")
      ->foreignKey("language_id", "Language", "uid", new SetNullStrategy());

    $queries[] = $sql->createTable("Session")
      ->addSerial("uid")
      ->addBool("active", true)
      ->addDateTime("expires")
      ->addInt("user_id")
      ->addString("ipAddress", 45)
      ->addString("os", 64)
      ->addString("browser", 64)
      ->addJson("data", false, '{}')
      ->addBool("stay_logged_in", true)
      ->primaryKey("uid", "user_id")
      ->foreignKey("user_id", "User", "uid", new CascadeStrategy());

    $queries[] = $sql->createTable("UserToken")
      ->addInt("user_id")
      ->addString("token", 36)
      ->addEnum("type", array("password_reset", "confirmation"))
      ->addDateTime("valid_until")
      ->foreignKey("user_id", "User", "uid", new CascadeStrategy());

    $queries[] = $sql->createTable("Group")
      ->addSerial("uid")
      ->addString("name", 32)
      ->primaryKey("uid")
      ->unique("name");

    $queries[] = $sql->insert("Group", array("uid", "name"))
      ->addRow(1, "Default")
      ->addRow(2, "Administrator")
      ->onDuplicateKeyStrategy(new UpdateStrategy(array("name" => new Column("name"))));

    $queries[] = $sql->createTable("UserGroup")
      ->addInt("user_id")
      ->addInt("group_id")
      ->unique("user_id", "group_id")
      ->foreignKey("user_id", "User", "uid")
      ->foreignKey("group_id", "Group", "uid");

    $queries[] = $sql->createTable("ApiKey")
      ->addSerial("uid")
      ->addInt("user_id")
      ->addBool("active", true)
      ->addString("api_key", 64)
      ->addDateTime("valid_until")
      ->primaryKey("uid")
      ->foreignKey("user_id", "User", "uid");

    return $queries;
  }
}

?>
