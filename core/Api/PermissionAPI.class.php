<?php

namespace Api {

  class PermissionAPI extends Request {
    protected function checkStaticPermission() {
      if (!$this->user->isLoggedIn() || !$this->user->hasGroup(USER_GROUP_ADMIN)) {
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
  use Driver\SQL\Condition\Compare;
  use Objects\User;

  class Check extends PermissionAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'method' => new StringType('method', 323)
      ));

      $this->isPublic = false;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $method = $this->getParam("method");
      $sql = $this->user->getSQL();
      $res = $sql->select("groups")
        ->from("ApiPermission")
        ->where(new Compare("method", $method))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if (empty($res)) {
          return true;
        }

        $groups = json_decode($res[0]["groups"]);
        if (empty($groups)) {
          return true;
        }

        if (!$this->user->isLoggedIn() || empty(array_intersect($groups, array_keys($this->user->getGroups())))) {
          return $this->createError("Permission denied.");
        }
      }

      return $this->success;
    }
  }

  class Fetch extends PermissionAPI {

    private array $groups;

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array());
    }

    private function fetchGroups() {
      $sql = $this->user->getSQL();
      $res = $sql->select("uid", "name")
        ->from("Group")
        ->orderBy("uid")
        ->ascending()
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->groups = array();
        foreach($res as $row) {
          $groupId = $row["uid"];
          $groupName = $row["name"];
          $this->groups[$groupId] = $groupName;
        }
      }

      return $this->success;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      if (!$this->checkStaticPermission()) {
        return false;
      }

      if (!$this->fetchGroups()) {
        return false;
      }

      $sql = $this->user->getSQL();
      $res = $sql->select("method", "groups")
        ->from("ApiPermission")
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $permissions = array();
        foreach ($res as $row) {
          $method = $row["method"];
          $groups = json_decode($row["groups"]);
          $permissions[] = array("method" => $method, "groups" => $groups);
        }
        $this->result["permissions"] = $permissions;
        $this->result["groups"] = $this->groups;
      }

      return $this->success;
    }
  }

  class Save extends PermissionAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'permissions' => new Parameter('permissions', Parameter::TYPE_ARRAY)
      ));
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      if (!$this->checkStaticPermission()) {
        return false;
      }



      return $this->success;
    }
  }
}