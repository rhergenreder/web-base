<?php

namespace Configuration;

use Driver\SQL\SQL;
use \Driver\SQL\Strategy\SetNullStrategy;
use \Driver\SQL\Strategy\CascadeStrategy;
use Objects\DatabaseEntity\DatabaseEntity;
use PHPUnit\Util\Exception;

class CreateDatabase extends DatabaseScript {

  public static function createQueries(SQL $sql): array {
    $queries = array();

    self::loadEntities($queries, $sql);

    $queries[] = $sql->insert("Language", array("code", "name"))
      ->addRow("en_US", 'American English')
      ->addRow("de_DE", 'Deutsch Standard');

    $queries[] = $sql->createTable("UserToken")
      ->addInt("user_id")
      ->addString("token", 36)
      ->addEnum("token_type", array("password_reset", "email_confirm", "invite", "gpg_confirm"))
      ->addDateTime("valid_until")
      ->addBool("used", false)
      ->foreignKey("user_id", "User", "id", new CascadeStrategy());

    $queries[] = $sql->insert("Group", array("name", "color"))
      ->addRow(USER_GROUP_MODERATOR_NAME, "#007bff")
      ->addRow(USER_GROUP_SUPPORT_NAME, "#28a745")
      ->addRow(USER_GROUP_ADMIN_NAME, "#dc3545");

    $queries[] = $sql->createTable("UserGroup")
      ->addInt("user_id")
      ->addInt("group_id")
      ->unique("user_id", "group_id")
      ->foreignKey("user_id", "User", "id", new CascadeStrategy())
      ->foreignKey("group_id", "Group", "id", new CascadeStrategy());

    $queries[] = $sql->createTable("UserNotification")
      ->addInt("user_id")
      ->addInt("notification_id")
      ->addBool("seen", false)
      ->foreignKey("user_id", "User", "id")
      ->foreignKey("notification_id", "Notification", "id")
      ->unique("user_id", "notification_id");

    $queries[] = $sql->createTable("GroupNotification")
      ->addInt("group_id")
      ->addInt("notification_id")
      ->addBool("seen", false)
      ->foreignKey("group_id", "Group", "id")
      ->foreignKey("notification_id", "Notification", "id")
      ->unique("group_id", "notification_id");

    $queries[] = $sql->createTable("Visitor")
      ->addInt("day")
      ->addInt("count", false, 1)
      ->addString("cookie", 26)
      ->unique("day", "cookie");

    $queries[] = $sql->createTable("Route")
      ->addSerial("id")
      ->addString("request", 128)
      ->addEnum("action", array("redirect_temporary", "redirect_permanently", "static", "dynamic"))
      ->addString("target", 128)
      ->addString("extra", 64, true)
      ->addBool("active", true)
      ->addBool("exact", true)
      ->primaryKey("id")
      ->unique("request");

    $queries[] = $sql->insert("Route", ["request", "action", "target", "extra", "exact"])
      ->addRow("/admin", "dynamic", "\\Documents\\Admin", NULL, false)
      ->addRow("/register", "dynamic", "\\Documents\\Account", json_encode(["account/register.twig"]), true)
      ->addRow("/confirmEmail", "dynamic", "\\Documents\\Account", json_encode(["account/confirm_email.twig"]), true)
      ->addRow("/acceptInvite", "dynamic", "\\Documents\\Account", json_encode(["account/accept_invite.twig"]), true)
      ->addRow("/resetPassword", "dynamic", "\\Documents\\Account", json_encode(["account/reset_password.twig"]), true)
      ->addRow("/login", "dynamic", "\\Documents\\Account", json_encode(["account/login.twig"]), true)
      ->addRow("/resendConfirmEmail", "dynamic", "\\Documents\\Account", json_encode(["account/resend_confirm_email.twig"]), true)
      ->addRow("/debug", "dynamic", "\\Documents\\Info", NULL, true)
      ->addRow("/", "static", "/static/welcome.html", NULL, true);

    $queries[] = $sql->createTable("Settings")
      ->addString("name", 32)
      ->addString("value", 1024, true)
      ->addBool("private", false) // these values are not returned from '/api/settings/get', but can be changed
      ->addBool("readonly", false) // these values are neither returned, nor can be changed from outside
      ->primaryKey("name");

    $settingsQuery = $sql->insert("Settings", array("name", "value", "private", "readonly"))
      // ->addRow("mail_enabled", "0") # this key will be set during installation
      ->addRow("mail_host", "", false, false)
      ->addRow("mail_port", "", false, false)
      ->addRow("mail_username", "", false, false)
      ->addRow("mail_password", "", true, false)
      ->addRow("mail_from", "", false, false)
      ->addRow("mail_last_sync", "", false, false)
      ->addRow("mail_footer", "", false, false);

    (Settings::loadDefaults())->addRows($settingsQuery);
    $queries[] = $settingsQuery;

    $queries[] = $sql->createTable("ContactRequest")
      ->addSerial("id")
      ->addString("from_name", 32)
      ->addString("from_email", 64)
      ->addString("message", 512)
      ->addString("messageId", 78, true) # null = don't sync with mails (usually if mail could not be sent)
      ->addDateTime("created_at", false, $sql->currentTimestamp())
      ->unique("messageId")
      ->primaryKey("id");

    $queries[] = $sql->createTable("ContactMessage")
      ->addSerial("id")
      ->addInt("request_id")
      ->addInt("user_id", true) # null = customer has sent this message
      ->addString("message", 512)
      ->addString("messageId", 78)
      ->addDateTime("created_at", false, $sql->currentTimestamp())
      ->addBool("read", false)
      ->unique("messageId")
      ->primaryKey("id")
      ->foreignKey("request_id", "ContactRequest", "id", new CascadeStrategy())
      ->foreignKey("user_id", "User", "id", new SetNullStrategy());

    $queries[] = $sql->createTable("ApiPermission")
      ->addString("method", 32)
      ->addJson("groups", true, '[]')
      ->addString("description", 128, false, "")
      ->primaryKey("method");

    $queries[] = $sql->createTable("MailQueue")
      ->addSerial("id")
      ->addString("from", 64)
      ->addString("to", 64)
      ->addString("subject")
      ->addString("body")
      ->addString("replyTo", 64, true)
      ->addString("replyName", 32, true)
      ->addString("gpgFingerprint", 64, true)
      ->addEnum("status", ["waiting","success","error"], false, 'waiting')
      ->addInt("retryCount", false, 5)
      ->addDateTime("nextTry", false, $sql->now())
      ->addString("errorMessage", NULL,  true)
      ->primaryKey("id");
    $queries = array_merge($queries, \Configuration\Patch\EntityLog_2021_04_08::createTableLog($sql, "MailQueue", 30));

    $queries[] = $sql->insert("ApiPermission", array("method", "groups", "description"))
      ->addRow("ApiKey/create", array(), "Allows users to create API-Keys for themselves")
      ->addRow("ApiKey/fetch", array(), "Allows users to list their API-Keys")
      ->addRow("ApiKey/refresh", array(), "Allows users to refresh their API-Keys")
      ->addRow("ApiKey/revoke", array(), "Allows users to revoke their API-Keys")
      ->addRow("Groups/fetch", array(USER_GROUP_SUPPORT, USER_GROUP_ADMIN), "Allows users to list all available groups")
      ->addRow("Groups/create", array(USER_GROUP_ADMIN), "Allows users to create a new groups")
      ->addRow("Groups/delete", array(USER_GROUP_ADMIN), "Allows users to delete a group")
      ->addRow("Routes/fetch", array(USER_GROUP_ADMIN), "Allows users to list all configured routes")
      ->addRow("Routes/save", array(USER_GROUP_ADMIN), "Allows users to create, delete and modify routes")
      ->addRow("Mail/test", array(USER_GROUP_SUPPORT, USER_GROUP_ADMIN), "Allows users to send a test email to a given address")
      ->addRow("Mail/Sync", array(USER_GROUP_SUPPORT, USER_GROUP_ADMIN), "Allows users to synchronize mails with the database")
      ->addRow("Settings/get", array(USER_GROUP_ADMIN), "Allows users to fetch server settings")
      ->addRow("Settings/set", array(USER_GROUP_ADMIN), "Allows users create, delete or modify server settings")
      ->addRow("Stats", array(USER_GROUP_ADMIN, USER_GROUP_SUPPORT), "Allows users to fetch server stats")
      ->addRow("User/create", array(USER_GROUP_ADMIN), "Allows users to create a new user, email address does not need to be confirmed")
      ->addRow("User/fetch", array(USER_GROUP_ADMIN, USER_GROUP_SUPPORT), "Allows users to list all registered users")
      ->addRow("User/get", array(USER_GROUP_ADMIN, USER_GROUP_SUPPORT), "Allows users to get information about a single user")
      ->addRow("User/invite", array(USER_GROUP_ADMIN), "Allows users to create a new user and send them an invitation link")
      ->addRow("User/edit", array(USER_GROUP_ADMIN), "Allows users to edit details and group memberships of any user")
      ->addRow("User/delete", array(USER_GROUP_ADMIN), "Allows users to delete any other user")
      ->addRow("Permission/fetch", array(USER_GROUP_ADMIN), "Allows users to list all API permissions")
      ->addRow("Visitors/stats", array(USER_GROUP_ADMIN, USER_GROUP_SUPPORT), "Allows users to see visitor statistics")
      ->addRow("Contact/respond", array(USER_GROUP_ADMIN, USER_GROUP_SUPPORT), "Allows users to respond to contact requests")
      ->addRow("Contact/fetch", array(USER_GROUP_ADMIN, USER_GROUP_SUPPORT), "Allows users to fetch all contact requests")
      ->addRow("Contact/get", array(USER_GROUP_ADMIN, USER_GROUP_SUPPORT), "Allows users to see messages within a contact request");

    self::loadPatches($queries, $sql);

    return $queries;
  }

  private static function loadPatches(&$queries, $sql) {
    $patchDirectory = './core/Configuration/Patch/';
    if (file_exists($patchDirectory) && is_dir($patchDirectory)) {
      $scan_arr = scandir($patchDirectory);
      $files_arr = array_diff($scan_arr, array('.', '..'));
      foreach ($files_arr as $file) {
        $suffix = ".class.php";
        if (endsWith($file, $suffix)) {
          $className = substr($file, 0, strlen($file) - strlen($suffix));
          $className = "\\Configuration\\Patch\\$className";
          $method = "$className::createQueries";
          $patchQueries = call_user_func($method, $sql);
          foreach ($patchQueries as $query) $queries[] = $query;
        }
      }
    }
  }

  private static function loadEntities(&$queries, $sql) {
    $entityDirectory = './core/Objects/DatabaseEntity/';
    if (file_exists($entityDirectory) && is_dir($entityDirectory)) {
      $scan_arr = scandir($entityDirectory);
      $files_arr = array_diff($scan_arr, array('.', '..'));
      $handlers = [];
      foreach ($files_arr as $file) {
        $suffix = ".class.php";
        if (endsWith($file, $suffix)) {
          $className = substr($file, 0, strlen($file) - strlen($suffix));
          if (!in_array($className, ["DatabaseEntity", "DatabaseEntityQuery", "DatabaseEntityHandler"])) {
            $className = "\\Objects\\DatabaseEntity\\$className";
            $reflectionClass = new \ReflectionClass($className);
            if ($reflectionClass->isSubclassOf(DatabaseEntity::class)) {
              $method = "$className::getHandler";
              $handler = call_user_func($method, $sql);
              $handlers[$handler->getTableName()] = $handler;
            }
          }
        }
      }

      $tableCount = count($handlers);
      $createdTables = [];
      while (!empty($handlers)) {
        $prevCount = $tableCount;
        $unmetDependenciesTotal = [];

        foreach ($handlers as $tableName => $handler) {
          $dependsOn = $handler->dependsOn();
          $unmetDependencies = array_diff($dependsOn, $createdTables);
          if (empty($unmetDependencies)) {
            $queries[] = $handler->getTableQuery();
            $createdTables[] = $tableName;
            unset($handlers[$tableName]);
          } else {
            $unmetDependenciesTotal = array_merge($unmetDependenciesTotal, $unmetDependencies);
          }
        }

        $tableCount = count($handlers);
        if ($tableCount === $prevCount) {
          throw new Exception("Circular or unmet table dependency detected. Unmet dependencies: "
            . implode(", ", $unmetDependenciesTotal));
        }
      }
    }
  }
}
