<?php

namespace Api {

  class GroupsAPI extends Request {

  }

}

namespace Api\Groups {

  use Api\GroupsAPI;
  use Api\Parameter\Parameter;

  class Fetch extends GroupsAPI {

    private int $groupCount;

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'page' => new Parameter('page', Parameter::TYPE_INT, true, 1),
        'count' => new Parameter('count', Parameter::TYPE_INT, true, 20)
      ));

      $this->loginRequired = true;
      $this->requiredGroup = array(USER_GROUP_SUPPORT, USER_GROUP_ADMIN);
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

}