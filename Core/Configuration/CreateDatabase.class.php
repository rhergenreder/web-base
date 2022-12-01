<?php

namespace Core\Configuration;

use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;
use Core\Objects\DatabaseEntity\Group;
use Core\Objects\DatabaseEntity\Language;
use Core\Objects\DatabaseEntity\Route;
use Core\Objects\Router\DocumentRoute;
use Core\Objects\Router\StaticFileRoute;
use PHPUnit\Util\Exception;

class CreateDatabase extends DatabaseScript {

  public static function createQueries(SQL $sql): array {
    $queries = array();

    self::loadEntities($queries, $sql);

    $queries[] = Language::getHandler($sql)->getInsertQuery([
      new Language(Language::AMERICAN_ENGLISH, "en_US", 'American English'),
      new Language(Language::GERMAN_STANDARD, "de_DE", 'Deutsch Standard'),
    ]);

    $queries[] = Group::getHandler($sql)->getInsertQuery([
      new Group(Group::ADMIN, Group::GROUPS[Group::ADMIN], "#dc3545"),
      new Group(Group::MODERATOR, Group::GROUPS[Group::MODERATOR], "#28a745"),
      new Group(Group::SUPPORT, Group::GROUPS[Group::SUPPORT], "#007bff"),
    ]);

    $queries[] = $sql->createTable("Visitor")
      ->addInt("day")
      ->addInt("count", false, 1)
      ->addString("cookie", 26)
      ->unique("day", "cookie");

    $queries[] = Route::getHandler($sql)->getInsertQuery([
      new DocumentRoute("/admin", false, \Core\Documents\Admin::class),
      new DocumentRoute("/register", true, \Core\Documents\Account::class, "account/register.twig"),
      new DocumentRoute("/confirmEmail", true, \Core\Documents\Account::class, "account/confirm_email.twig"),
      new DocumentRoute("/acceptInvite", true, \Core\Documents\Account::class, "account/accept_invite.twig"),
      new DocumentRoute("/resetPassword", true, \Core\Documents\Account::class, "account/reset_password.twig"),
      new DocumentRoute("/login", true, \Core\Documents\Account::class, "account/login.twig"),
      new DocumentRoute("/resendConfirmEmail", true, \Core\Documents\Account::class, "account/resend_confirm_email.twig"),
      new DocumentRoute("/debug", true, \Core\Documents\Info::class),
      new StaticFileRoute("/", true, "/static/welcome.html"),
    ]);

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
      ->addRow("Settings/generateJWT", array(Group::ADMIN), "Allows users generate a new jwt key")
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
            if ($reflectionClass->getParentClass()?->getName() === DatabaseEntity::class) {
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
