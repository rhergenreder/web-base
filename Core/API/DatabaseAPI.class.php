<?php

namespace Core\API {

  use Core\Objects\Context;

  abstract class DatabaseAPI extends Request {

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }

  }

}

namespace Core\API\Database {

  use Core\API\DatabaseAPI;
  use Core\API\Parameter\RegexType;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;
  use Core\Objects\DatabaseEntity\Group;

  class Status extends DatabaseAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, []);
    }

    protected function _execute(): bool {
      $sql = $this->context->getSQL();
      $status = $sql->getStatus();
      $this->result["status"] = $status;
      return true;
    }

    public static function getDescription(): string {
      return "Allows users to view the database status";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN];
    }
  }

  class Migrate extends DatabaseAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "className" => new RegexType("className", "[a-zA-Z][a-zA-Z0-9]{0,256}")
      ]);
    }

    protected function _execute(): bool {
      $className = $this->getParam("className");
      $class = null;
      foreach (["Site", "Core"] as $baseDir) {
        $classPath = "\\$baseDir\\Objects\\DatabaseEntity\\$className";
        if (isClass($classPath)) {
          $class = new \ReflectionClass($classPath);
          if (!$class->isSubclassOf(DatabaseEntity::class)) {
            $class = null;
            continue;
          }
          break;
        }
      }

      if ($class === null) {
        return $this->createError("Class not found");
      }

      $sql = $this->context->getSQL();
      $handler = call_user_func("$classPath::getHandler", $sql, null, true);
      $persistables = array_merge([
        $handler->getTableName() => $handler
      ], $handler->getNMRelations());

      foreach ($persistables as $tableName => $persistable) {
        // first check if table exists
        if (!$sql->tableExists($tableName)) {
          $sql->startTransaction();
          $success = true;
          try {
            foreach ($persistable->getCreateQueries($sql) as $query) {
              if (!$query->execute()) {
                $this->lastError = "Error migrating table: " . $sql->getLastError();
                $success = false;
                break;
              }
            }
          } catch (\Exception $ex) {
            $success = false;
            $this->lastError = "Error migrating table: " . $ex->getMessage();
          }

          if (!$success) {
            $sql->rollback();
            return false;
          } else {
            $sql->commit();
          }
        } else {
          // TODO: Alter table ...
        }
      }

      return true;
    }

    public static function getDescription(): string {
      return "Allows users to migrate the database structure";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN];
    }
  }
}