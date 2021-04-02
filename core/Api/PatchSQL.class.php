<?php

namespace Api;

use Api\Parameter\StringType;
use Configuration\DatabaseScript;
use Objects\User;

class PatchSQL extends Request {

  public function __construct(User $user, bool $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      "className" => new StringType("className", 64)
    ));
    $this->loginRequired = true;
    $this->csrfTokenRequired = false;
  }

  public function execute($values = array()): bool {
    if (!parent::execute($values)) {
      return false;
    }

    $className = $this->getParam("className");
    $fullClassName = "\\Configuration\\Patch\\" . $className;
    $path = getClassPath($fullClassName, true);
    if (!file_exists($path)) {
      return $this->createError("File not found");
    }

    if(!class_exists($fullClassName)) {
      return $this->createError("Class not found.");
    }

    try {
      $reflection = new \ReflectionClass($fullClassName);
      if (!$reflection->isInstantiable()) {
        return $this->createError("Class is not instantiable");
      }

      if (!$reflection->isSubclassOf(DatabaseScript::class)) {
        return $this->createError("Not a database script.");
      }

      $sql = $this->user->getSQL();
      $obj = $reflection->newInstance();
      $queries = $obj->createQueries($sql);
      if (!is_array($queries)) {
        return $this->createError("Database script returned invalid values");
      }

      foreach($queries as $query) {
        if (!$query->execute()) {
          return $this->createError("Query error: " . $sql->getLastError());
        }
      }

      $this->success = true;
    } catch (\ReflectionException $e) {
      return $this->createError("Error reflecting class: " . $e->getMessage());
    }

    return $this->success;
  }
}