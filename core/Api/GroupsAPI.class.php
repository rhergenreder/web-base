<?php

namespace Api {

  use Driver\SQL\Condition\Compare;

  class GroupsAPI extends Request {

    protected function groupExists($name) {
      $sql = $this->user->getSQL();
      $res = $sql->select($sql->count())
        ->from("Group")
        ->where(new Compare("name", $name))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success && $res[0]["count"] > 0;
    }
  }
}

namespace Api\Groups {

  use Api\GroupsAPI;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Driver\SQL\Condition\Compare;

  class Fetch extends GroupsAPI {

    private int $groupCount;

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'page' => new Parameter('page', Parameter::TYPE_INT, true, 1),
        'count' => new Parameter('count', Parameter::TYPE_INT, true, 20)
      ));

      $this->groupCount = 0;
    }

    private function getGroupCount() {

      $sql = $this->user->getSQL();
      $res = $sql->select($sql->count())->from("Group")->execute();
      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->groupCount = $res[0]["count"];
      }

      return $this->success;
    }

    public function execute($values = array()) {
      if(!parent::execute($values)) {
        return false;
      }

      $page = $this->getParam("page");
      if($page < 1) {
        return $this->createError("Invalid page count");
      }

      $count = $this->getParam("count");
      if($count < 1 || $count > 50) {
        return $this->createError("Invalid fetch count");
      }

      if (!$this->getGroupCount()) {
        return false;
      }

      $sql = $this->user->getSQL();
      $res = $sql->select("Group.uid as groupId", "Group.name as groupName", "Group.color as groupColor", $sql->count("UserGroup.user_id"))
        ->from("Group")
        ->leftJoin("UserGroup", "UserGroup.group_id", "Group.uid")
        ->groupBy("Group.uid")
        ->orderBy("Group.uid")
        ->ascending()
        ->limit($count)
        ->offset(($page - 1) * $count)
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if($this->success) {
        $this->result["groups"] = array();
        foreach($res as $row) {
          $groupId = intval($row["groupId"]);
          $groupName = $row["groupName"];
          $groupColor = $row["groupColor"];
          $memberCount = $row["usergroup_user_id_count"];
          $this->result["groups"][$groupId] = array(
            "name" => $groupName,
            "memberCount" => $memberCount,
            "color" => $groupColor,
          );
        }
        $this->result["pageCount"] = intval(ceil($this->groupCount / $count));
        $this->result["totalCount"] = $this->groupCount;
      }

      return $this->success;
    }
  }

  class Create extends GroupsAPI {
    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'name' => new StringType('name', 32),
        'color' => new StringType('color', 10),
      ));
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $name = $this->getParam("name");
      if (preg_match("/^[a-zA-Z][a-zA-Z0-9_-]*$/", $name) !== 1) {
        return $this->createError("Invalid name");
      }

      $color = $this->getParam("color");
      if (preg_match("/^#[a-fA-F0-9]{3,6}$/", $color) !== 1) {
        return $this->createError("Invalid color");
      }

      $exists = $this->groupExists($name);
      if (!$this->success) {
        return false;
      } else if ($exists) {
        return $this->createError("A group with this name already exists");
      }

      $sql = $this->user->getSQL();
      $res = $sql->insert("Group", array("name", "color"))
        ->addRow($name, $color)
        ->returning("uid")
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["uid"] = $sql->getLastInsertId();
      }

      return $this->success;
    }
  }

  class Delete extends GroupsAPI {
    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'uid' => new Parameter('uid', Parameter::TYPE_INT)
      ));
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $id = $this->getParam("uid");
      if (in_array($id, DEFAULT_GROUPS)) {
        return $this->createError("You cannot delete a default group.");
      }

      $sql = $this->user->getSQL();
      $res = $sql->select($sql->count())
        ->from("Group")
        ->where(new Compare("uid", $id))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success && $res[0]["count"] === 0) {
        return $this->createError("This group does not exist.");
      }

      $res = $sql->delete("Group")->where(new Compare("uid", $id))->execute();
      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }
}