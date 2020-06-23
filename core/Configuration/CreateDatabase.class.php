<?php

namespace Configuration;

use Driver\SQL\SQL;
use \Driver\SQL\Strategy\SetNullStrategy;
use \Driver\SQL\Strategy\CascadeStrategy;

class CreateDatabase {

  public static function createQueries(SQL $sql) {
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
      ->addRow(2, "de_DE", 'Deutsch Standard');

    $queries[] = $sql->createTable("User")
      ->addSerial("uid")
      ->addString("email", 64, true)
      ->addString("name", 32)
      ->addString("password", 128)
      ->addInt("language_id", true, 1)
      ->addDateTime("registered_at", false, $sql->currentTimestamp())
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
      ->addString("csrf_token", 16 )
      ->primaryKey("uid", "user_id")
      ->foreignKey("user_id", "User", "uid", new CascadeStrategy());

    $queries[] = $sql->createTable("UserInvitation")
      ->addString("username",32)
      ->addString("email",32)
      ->addString("token",36)
      ->addDateTime("valid_until");

    $queries[] = $sql->createTable("UserToken")
      ->addInt("user_id")
      ->addString("token", 36)
      ->addEnum("token_type", array("password_reset", "email_confirm"))
      ->addDateTime("valid_until")
      ->addBool("used", false)
      ->foreignKey("user_id", "User", "uid", new CascadeStrategy());

    $queries[] = $sql->createTable("Group")
      ->addSerial("uid")
      ->addString("name", 32)
      ->addString("color", 10)
      ->primaryKey("uid")
      ->unique("name");

    $queries[] = $sql->insert("Group", array("uid", "name", "color"))
      ->addRow(USER_GROUP_MODERATOR, USER_GROUP_MODERATOR_NAME, "#007bff")
      ->addRow(USER_GROUP_SUPPORT, USER_GROUP_SUPPORT_NAME, "#28a745")
      ->addRow(USER_GROUP_ADMIN, USER_GROUP_ADMIN_NAME, "#dc3545");

    $queries[] = $sql->createTable("UserGroup")
      ->addInt("user_id")
      ->addInt("group_id")
      ->unique("user_id", "group_id")
      ->foreignKey("user_id", "User", "uid", new CascadeStrategy())
      ->foreignKey("group_id", "Group", "uid", new CascadeStrategy());

    $queries[] = $sql->createTable("Notification")
      ->addSerial("uid")
      ->addDateTime("created_at", false, $sql->currentTimestamp())
      ->addString("title", 32)
      ->addString("message", 256)
      ->primaryKey("uid");

    $queries[] = $sql->createTable("UserNotification")
      ->addInt("user_id")
      ->addInt("notification_id")
      ->addBool("seen")
      ->foreignKey("user_id", "User", "uid")
      ->foreignKey("notification_id", "Notification", "uid")
      ->unique("user_id", "notification_id");

    $queries[] = $sql->createTable("GroupNotification")
      ->addInt("group_id")
      ->addInt("notification_id")
      ->addBool("seen")
      ->foreignKey("group_id", "Group", "uid")
      ->foreignKey("notification_id", "Notification", "uid")
      ->unique("group_id", "notification_id");

    $queries[] = $sql->createTable("ApiKey")
      ->addSerial("uid")
      ->addInt("user_id")
      ->addBool("active", true)
      ->addString("api_key", 64)
      ->addDateTime("valid_until")
      ->primaryKey("uid")
      ->foreignKey("user_id", "User", "uid");

    $queries[] = $sql->createTable("Visitor")
      ->addInt("month")
      ->addInt("count", false, 1)
      ->addString("cookie", 26)
      ->unique("month", "cookie");

    $queries[] = $sql->createTable("Route")
      ->addSerial("uid")
      ->addString("request", 128)
      ->addEnum("action", array("redirect_temporary", "redirect_permanently", "static", "dynamic"))
      ->addString("target", 128)
      ->addString("extra", 64, true)
      ->addBool("active", true)
      ->primaryKey("uid");

    $queries[] = $sql->insert("Route", array("request", "action", "target", "extra"))
      ->addRow("^/admin(/.*)?$", "dynamic", "\\Documents\\Admin", NULL)
      ->addRow("^/register(/)?$", "dynamic", "\\Documents\\Account", "\\Views\\Account\\Register")
      ->addRow("^/confirmEmail(/)?$", "dynamic", "\\Documents\\Account", "\\Views\\Account\\ConfirmEmail")
      ->addRow("^/acceptInvite(/)?$", "dynamic", "\\Documents\\Account", "\\Views\\Account\\AcceptInvite")
      ->addRow("^/$", "static", "/static/welcome.html", NULL);

    return $queries;
  }
}
