<?php

namespace Api\Groups;

use \Api\Parameter\Parameter;
use \Api\Request;

class Fetch extends Request {

  const SELECT_SIZE = 10;

  private int $groupCount;

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      'page' => new Parameter('page', Parameter::TYPE_INT, true, 1)
    ));

    $this->loginRequired = true;
    $this->requiredGroup = USER_GROUP_ADMIN;
    $this->csrfTokenRequired = true;
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

    if (!$this->getGroupCount()) {
      return false;
    }

    $sql = $this->user->getSQL();
    $res = $sql->select("Group.uid as groupId", "Group.name as groupName", $sql->count("UserGroup.user_id"))
      ->from("Group")
      ->innerJoin("UserGroup", "UserGroup.group_id", "Group.uid")
      ->groupBy("Group.uid")
      ->orderBy("Group.uid")
      ->ascending()
      ->limit(Fetch::SELECT_SIZE)
      ->offset(($page - 1) * Fetch::SELECT_SIZE)
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if($this->success) {
      $this->result["groups"] = array();
      foreach($res as $row) {
        $groupId = intval($row["groupId"]);
        $groupName = $row["groupName"];
        $memberCount = $row["usergroup_user_id_count"];
        $this->result["groups"][$groupId] = array(
          "name" => $groupName,
          "memberCount" => $memberCount
        );
      }
      $this->result["pageCount"] = intval(ceil($this->groupCount / Fetch::SELECT_SIZE));
    }

    return $this->success;
  }
}
