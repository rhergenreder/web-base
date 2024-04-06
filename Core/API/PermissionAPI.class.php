<?php

namespace Core\API {

  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Group;

  abstract class PermissionAPI extends Request {

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }

    protected function checkStaticPermission(): bool {
      // hardcoded permission checking
      $user = $this->context->getUser();
      if (!$user || !$user->hasGroup(Group::ADMIN)) {
        return $this->createError("Permission denied.");
      }

      return true;
    }

    protected function isRestricted(string $method): bool {
      return in_array(strtolower($method), ["permission/update", "permission/delete"]);
    }
  }
}

namespace Core\API\Permission {

  use Core\API\Parameter\ArrayType;
  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\API\PermissionAPI;
  use Core\Driver\SQL\Column\Column;
  use Core\Driver\SQL\Condition\CondIn;
  use Core\Driver\SQL\Condition\CondLike;
  use Core\Driver\SQL\Query\Insert;
  use Core\Driver\SQL\Strategy\UpdateStrategy;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Group;

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
          if (!$currentUser) {
            $this->result["loggedIn"] = false;
          }

          http_response_code(401);
          return $this->createError("Permission denied.");
        }

        // user would have required groups, check for 2fa-state
        if ($currentUser && !$this->check2FA()) {
          http_response_code(401);
          return false;
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
      $res = $sql->select("method", "groups", "description", "is_core")
        ->from("ApiPermission")
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $permissions = [];
        foreach ($res as $row) {
          $method = $row["method"];
          $description = $row["description"];
          $groups = json_decode($row["groups"]);
          $isCore = $row["is_core"];
          $permissions[] = [
            "method" => $method,
            "groups" => $groups,
            "description" => $description,
            "is_core" => $isCore
          ];
        }
        $this->result["permissions"] = $permissions;
        $this->result["groups"] = $this->groups;
      }

      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN], "Allows users to fetch API permissions", true);
    }
  }

  class Update extends PermissionAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "method" => new StringType("method", 32, false),
        "groups" => new ArrayType("groups", Parameter::TYPE_INT, true, false),
        "description" => new StringType("description", 128, true, null),
      ]);
    }

    protected function _execute(): bool {

      if (!$this->checkStaticPermission()) {
        return false;
      }

      $sql = $this->context->getSQL();
      $method = $this->getParam("method");
      $description = $this->getParam("description");
      if ($this->isRestricted($method)) {
        return $this->createError("This method cannot be updated.");
      }

      $groupIds = array_unique($this->getParam("groups"));
      if (!empty($groupIds)) {
        sort($groupIds);
        $availableGroups = Group::findAll($sql, new CondIn(new Column("id"), $groupIds));
        foreach ($groupIds as $groupId) {
          if (!isset($availableGroups[$groupId])) {
            return $this->createError("Group with id=$groupId does not exist.");
          }
        }
      }

      if ($description === null) {
        $updateQuery = $sql->insert("ApiPermission", ["method", "groups", "is_core"])
          ->onDuplicateKeyStrategy(new UpdateStrategy(["method"], ["groups" => $groupIds]))
          ->addRow($method, $groupIds, false);
      } else {
        $updateQuery = $sql->insert("ApiPermission", ["method", "groups", "is_core", "description"])
          ->onDuplicateKeyStrategy(new UpdateStrategy(["method"], ["groups" => $groupIds, "description" => $description]))
          ->addRow($method, $groupIds, false, $description);
      }

      $this->success = $updateQuery->execute() !== false;
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(
        self::getEndpoint(), [Group::ADMIN],
        "Allows users to modify API permissions. This is restricted to the administrator and cannot be changed",
        true
      );
    }
  }

  class Delete extends PermissionAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "method" => new StringType("method", 32, false),
      ]);
    }

    protected function _execute(): bool {

      if (!$this->checkStaticPermission()) {
        return false;
      }

      $sql = $this->context->getSQL();
      $method = $this->getParam("method");
      if ($this->isRestricted($method)) {
        return $this->createError("This method cannot be deleted.");
      }

      $res = $sql->select("method")
        ->from("ApiPermission")
        ->whereEq("method", $method)
        ->execute();

      $this->success = $res !== false;
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if (!$res) {
          return $this->createError("This method was not configured yet");
        } else {
          $res = $sql->delete("ApiPermission")
            ->whereEq("method", $method)
            ->execute();
          $this->success = $res !== false;
          $this->lastError = $sql->getLastError();
        }
      }

      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(
        self::getEndpoint(), [Group::ADMIN],
        "Allows users to delete API permissions. This is restricted to the administrator and cannot be changed",
        true
      );
    }
  }
}