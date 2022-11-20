<?php

namespace Core\Configuration;

use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;
use Core\Objects\DatabaseEntity\Group;
use Core\Objects\DatabaseEntity\Language;
use PHPUnit\Util\Exception;

class CreateDatabase extends DatabaseScript {

  public static function createQueries(SQL $sql): array {
    $queries = array();

    self::loadEntities($queries, $sql);

    $queries[] = Language::getHandler($sql)->getInsertQuery([
      new Language(Language::AMERICAN_ENGLISH, "en_US", 'American English'),
      new Language(Language::AMERICAN_ENGLISH, "de_DE", 'Deutsch Standard'),
    ]);

    $queries[] = Group::getHandler($sql)->getInsertQuery([
      new Group(Group::ADMIN, Group::GROUPS[Group::ADMIN], "#007bff"),
      new Group(Group::MODERATOR, Group::GROUPS[Group::MODERATOR], "#28a745"),
      new Group(Group::SUPPORT, Group::GROUPS[Group::SUPPORT], "#dc3545"),
    ]);

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
      ->addRow("/admin", "dynamic", "\\Core\\Documents\\Admin", NULL, false)
      ->addRow("/register", "dynamic", "\\Core\\Documents\\Account", json_encode(["account/register.twig"]), true)
      ->addRow("/confirmEmail", "dynamic", "\\Core\\Documents\\Account", json_encode(["account/confirm_email.twig"]), true)
      ->addRow("/acceptInvite", "dynamic", "\\Core\\Documents\\Account", json_encode(["account/accept_invite.twig"]), true)
      ->addRow("/resetPassword", "dynamic", "\\Core\\Documents\\Account", json_encode(["account/reset_password.twig"]), true)
      ->addRow("/login", "dynamic", "\\Core\\Documents\\Account", json_encode(["account/login.twig"]), true)
      ->addRow("/resendConfirmEmail", "dynamic", "\\Core\\Documents\\Account", json_encode(["account/resend_confirm_email.twig"]), true)
      ->addRow("/debug", "dynamic", "\\Core\\Documents\\Info", NULL, true)
      ->addRow("/", "static", "/static/welcome.html", NULL, true);

    $queries[] = $sql->createTable("Settings")
      ->addString("name", 32)
      ->addString("value", 1024, true)
      ->addBool("private", false) // these values are not returned from '/api/settings/get', but can be changed
      ->addBool("readonly", false) // these values are neither returned, nor can be changed from outside
      ->primaryKey("name");
    $settingsQuery = $sql->insert("Settings", array("name", "value", "private", "readonly"));
    (Settings::loadDefaults())->addRows($settingsQuery);
    $queries[] = $settingsQuery;

    $queries[] = $sql->createTable("ApiPermission")
      ->addString("method", 32)
      ->addJson("groups", true, '[]')
      ->addString("description", 128, false, "")
      ->primaryKey("method");

    $queries[] = $sql->insert("ApiPermission", array("method", "groups", "description"))
      ->addRow("ApiKey/create", array(), "Allows users to create API-Keys for themselves")
      ->addRow("ApiKey/fetch", array(), "Allows users to list their API-Keys")
      ->addRow("ApiKey/refresh", array(), "Allows users to refresh their API-Keys")
      ->addRow("ApiKey/revoke", array(), "Allows users to revoke their API-Keys")
      ->addRow("Groups/fetch", array(Group::SUPPORT, Group::ADMIN), "Allows users to list all available groups")
      ->addRow("Groups/create", array(Group::ADMIN), "Allows users to create a new groups")
      ->addRow("Groups/delete", array(Group::ADMIN), "Allows users to delete a group")
      ->addRow("Routes/fetch", array(Group::ADMIN), "Allows users to list all configured routes")
      ->addRow("Routes/save", array(Group::ADMIN), "Allows users to create, delete and modify routes")
      ->addRow("Mail/test", array(Group::SUPPORT, Group::ADMIN), "Allows users to send a test email to a given address")
      ->addRow("Mail/Sync", array(Group::SUPPORT, Group::ADMIN), "Allows users to synchronize mails with the database")
      ->addRow("Settings/get", array(Group::ADMIN), "Allows users to fetch server settings")
      ->addRow("Settings/set", array(Group::ADMIN), "Allows users create, delete or modify server settings")
      ->addRow("Stats", array(Group::ADMIN, Group::SUPPORT), "Allows users to fetch server stats")
      ->addRow("User/create", array(Group::ADMIN), "Allows users to create a new user, email address does not need to be confirmed")
      ->addRow("User/fetch", array(Group::ADMIN, Group::SUPPORT), "Allows users to list all registered users")
      ->addRow("User/get", array(Group::ADMIN, Group::SUPPORT), "Allows users to get information about a single user")
      ->addRow("User/invite", array(Group::ADMIN), "Allows users to create a new user and send them an invitation link")
      ->addRow("User/edit", array(Group::ADMIN), "Allows users to edit details and group memberships of any user")
      ->addRow("User/delete", array(Group::ADMIN), "Allows users to delete any other user")
      ->addRow("Permission/fetch", array(Group::ADMIN), "Allows users to list all API permissions")
      ->addRow("Visitors/stats", array(Group::ADMIN, Group::SUPPORT), "Allows users to see visitor statistics")
      ->addRow("Contact/respond", array(Group::ADMIN, Group::SUPPORT), "Allows users to respond to contact requests")
      ->addRow("Contact/fetch", array(Group::ADMIN, Group::SUPPORT), "Allows users to fetch all contact requests")
      ->addRow("Contact/get", array(Group::ADMIN, Group::SUPPORT), "Allows users to see messages within a contact request")
      ->addRow("Logs/get", [Group::ADMIN], "Allows users to fetch system logs");

    self::loadPatches($queries, $sql);

    return $queries;
  }

  private static function loadPatches(&$queries, $sql) {
    $baseDirs = ["Core", "Site"];
    foreach ($baseDirs as $baseDir) {
      $patchDirectory = "./$baseDir/Configuration/Patch/";
      if (file_exists($patchDirectory) && is_dir($patchDirectory)) {
        $scan_arr = scandir($patchDirectory);
        $files_arr = array_diff($scan_arr, array('.', '..'));
        foreach ($files_arr as $file) {
          $suffix = ".class.php";
          if (endsWith($file, $suffix)) {
            $className = substr($file, 0, strlen($file) - strlen($suffix));
            $className = "\\$baseDir\\Configuration\\Patch\\$className";
            $method = "$className::createQueries";
            $patchQueries = call_user_func($method, $sql);
            foreach ($patchQueries as $query) $queries[] = $query;
          }
        }
      }
    }
  }

  public static function loadEntities(&$queries, $sql) {
    $persistables = [];
    $baseDirs = ["Core", "Site"];
    foreach ($baseDirs as $baseDir) {

      $entityDirectory = "./$baseDir/Objects/DatabaseEntity/";
      if (file_exists($entityDirectory) && is_dir($entityDirectory)) {
        $scan_arr = scandir($entityDirectory);
        $files_arr = array_diff($scan_arr, array('.', '..'));
        foreach ($files_arr as $file) {
          $suffix = ".class.php";
          if (endsWith($file, $suffix)) {
            $className = substr($file, 0, strlen($file) - strlen($suffix));
            $className = "\\$baseDir\\Objects\\DatabaseEntity\\$className";
            $reflectionClass = new \ReflectionClass($className);
            if ($reflectionClass->isSubclassOf(DatabaseEntity::class)) {
              $method = "$className::getHandler";
              $handler = call_user_func($method, $sql);
              $persistables[$handler->getTableName()] = $handler;

              foreach ($handler->getNMRelations() as $nmTableName => $nmRelation) {
                $persistables[$nmTableName] = $nmRelation;
              }
            }
          }
        }
      }

      $tableCount = count($persistables);
      $createdTables = [];
      while (!empty($persistables)) {
        $prevCount = $tableCount;
        $unmetDependenciesTotal = [];

        foreach ($persistables as $tableName => $persistable) {
          $dependsOn = $persistable->dependsOn();
          $unmetDependencies = array_diff($dependsOn, $createdTables);
          if (empty($unmetDependencies)) {
            $queries = array_merge($queries, $persistable->getCreateQueries($sql));
            $createdTables[] = $tableName;
            unset($persistables[$tableName]);
          } else {
            $unmetDependenciesTotal = array_merge($unmetDependenciesTotal, $unmetDependencies);
          }
        }

        $tableCount = count($persistables);
        if ($tableCount === $prevCount) {
          throw new Exception("Circular or unmet table dependency detected. Unmet dependencies: "
            . implode(", ", $unmetDependenciesTotal));
        }
      }
    }
  }
}
