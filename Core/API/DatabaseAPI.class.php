<?php

namespace Core\API {

  abstract class DatabaseAPI extends Request {

  }

}

namespace Core\API\Database {

  use Core\API\DatabaseAPI;
  use Core\API\Parameter\StringType;
  use Core\Objects\Context;

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
  }

  class Migrate extends DatabaseAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "className" => new StringType("className", 256)
      ]);
    }

    protected function _execute(): bool {
      $className = $this->getParam("className");
      if (!preg_match("/[a-zA-Z0-9]+/", $className)) {
        return $this->createError("Invalid class name");
      }

      $class = null;
      foreach (["Site", "Core"] as $baseDir) {
        $classPath = "\\$baseDir\\Objects\\DatabaseEntity\\$className";
        if (isClass($classPath)) {
          $class = new \ReflectionClass($classPath);
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
  }
}