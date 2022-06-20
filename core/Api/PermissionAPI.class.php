<?php

namespace Api {

  use Objects\Context;

  abstract class PermissionAPI extends Request {

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }

    protected function checkStaticPermission(): bool {
      $user = $this->context->getUser();
      if (!$user || !$user->hasGroup(USER_GROUP_ADMIN)) {
        return $this->createError("Permission denied.");
      }

      return true;
    }
  }
}

namespace Api\Permission {

  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\PermissionAPI;
  use Driver\SQL\Column\Column;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondIn;
  use Driver\SQL\Condition\CondLike;
  use Driver\SQL\Condition\CondNot;
  use Driver\SQL\Strategy\UpdateStrategy;
  use Objects\Context;
  use Objects\DatabaseEntity\Group;
  use Objects\DatabaseEntity\User;

  class Check extends PermissionAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'method' => new StringType('method', 323)
      ));

      $this->isPublic = false;
    }

    public function _execute(): bool {

      $method = $this->getParam("method");
      $sql = $this->context->getSQL();
      $res = $sql->select("groups")
        ->from("ApiPermission")
        ->where(new CondLike($method, new Column("method")))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if (empty($res) || !is_array($res)) {
          return true;
        }

        $groups = json_decode($res[0]["groups"]);
        if (empty($groups)) {
          return true;
        }

        $currentUser = $this->context->getUser();
        $userGroups = $currentUser ? $currentUser->getGroups() : [];
        if (empty($userGroups) || empty(array_intersect($groups, array_keys($userGroups)))) {
          http_response_code(401);
          return $this->createError("Permission denied.");
        }
      }

      return $this->success;
    }
  }

  class Fetch extends PermissionAPI {

    private ?array $groups;

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array());
    }

    private function fetchGroups(): bool {
      $sql = $this->context->getSQL();
      $this->groups = Group::findAll($sql);
      $this->success = ($this->groups !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    public function _execute(): bool {

      if (!$this->fetchGroups()) {
        return false;
      }

      $sql = $this->context->getSQL();
      $res = $sql->select("method", "groups", "description")
        ->from("ApiPermission")
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $permissions = array();
        foreach ($res as $row) {
          $method = $row["method"];
          $description = $row["description"];
          $groups = json_decode($row["groups"]);
          $permissions[] = array(
            "method" => $method,
            "groups" => $groups,
            "description" => $description
          );
        }
        $this->result["permissions"] = $permissions;
        $this->result["groups"] = $this->groups;
      }

      return $this->success;
    }
  }

  class Save extends PermissionAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'permissions' => new Parameter('permissions', Parameter::TYPE_ARRAY)
      ));
    }

    public function _execute(): bool {

      if (!$this->checkStaticPermission()) {
        return false;
      }

      $permissions = $this->getParam("permissions");
      $sql = $this->context->getSQL();
      $methodParam = new StringType('method', 32);
      $groupsParam = new Parameter('groups', Parameter::TYPE_ARRAY);

      $updateQuery = $sql->insert("ApiPermission", array("method", "groups"))
        ->onDuplicateKeyStrategy(new UpdateStrategy(array("method"), array("groups" => new Column("groups"))));

      $insertedMethods = array();

      foreach ($permissions as $permission) {
        if (!is_array($permission)) {
          return $this->createError("Invalid data type found in parameter: permissions, expected: object");
        } else if (!isset($permission["method"]) || !array_key_exists("groups", $permission)) {
          return $this->createError("Invalid object found in parameter: permissions, expected keys 'method' and 'groups'");
        } else if (!$methodParam->parseParam($permission["method"])) {
          $expectedType = $methodParam->getTypeName();
          return $this->createError("Invalid data type found for attribute 'method', expected: $expectedType");
        } else if (!$groupsParam->parseParam($permission["groups"])) {
          $expectedType = $groupsParam->getTypeName();
          return $this->createError("Invalid data type found for attribute 'groups', expected: $expectedType");
        } else if (empty(trim($methodParam->value))) {
          return $this->createError("Method cannot be empty.");
        } else {
          $method = $methodParam->value;
          $groups = $groupsParam->value;
          $updateQuery->addRow($method, $groups);
          $insertedMethods[] = $method;
        }
      }

      if (!empty($permissions)) {
        $res = $updateQuery->execute();
        $this->success = ($res !== FALSE);
        $this->lastError = $sql->getLastError();
      }

      if ($this->success) {
        $res = $sql->delete("ApiPermission")
          ->where(new Compare("description", "")) // only delete non default permissions
          ->where(new CondNot(new CondIn(new Column("method"), $insertedMethods)))
          ->execute();

        $this->success = ($res !== FALSE);
        $this->lastError = $sql->getLastError();
      }

      return $this->success;
    }
  }
}