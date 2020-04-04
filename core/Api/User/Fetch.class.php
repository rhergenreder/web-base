<?php

namespace Api\User;

use Api\Parameter\Parameter;
use \Api\Request;

class Fetch extends Request {

  const SELECT_SIZE = 20;

  private int $userCount;

  public function __construct($user, $externalCall = false) {

    parent::__construct($user, $externalCall, array(
      'page' => new Parameter('page', Parameter::TYPE_INT, true, 1)
    ));

    $this->loginRequired = true;
    $this->requiredGroup = USER_GROUP_ADMIN;
    $this->userCount = 0;
  }

  private function getUserCount() {

    $sql = $this->user->getSQL();
    $res = $sql->select($sql->count())->from("User")->execute();
    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if ($this->success) {
      $this->userCount = $res[0]["count"];
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

    if (!$this->getUserCount()) {
      return false;
    }

    $sql = $this->user->getSQL();
    $res = $sql->select("User.uid as userId", "User.name", "User.email", "User.registered_at",
                        "Group.uid as groupId", "Group.name as groupName")
      ->from("User")
      ->leftJoin("UserGroup", "User.uid", "UserGroup.user_id")
      ->leftJoin("Group", "Group.uid", "UserGroup.group_id")
      ->orderBy("User.uid")
      ->ascending()
      ->limit(Fetch::SELECT_SIZE)
      ->offset(($page - 1) * Fetch::SELECT_SIZE)
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if($this->success) {
      $this->result["users"] = array();
      foreach($res as $row) {
        $userId = intval($row["userId"]);
        $groupId = intval($row["groupId"]);
        $groupName = $row["groupName"];
        if (!isset($this->result["users"][$userId])) {
          $this->result["users"][$userId] = array(
            "uid" => $userId,
            "name" => $row["name"],
            "email" => $row["email"],
            "registered_at" => $row["registered_at"],
            "groups" => array(),
          );
        }

        if(!is_null($groupId)) {
          $this->result["users"][$userId]["groups"][$groupId] = $groupName;
        }
      }
      $this->result["pages"] = intval(ceil($this->userCount / Fetch::SELECT_SIZE));
    }

    return $this->success;
  }
}
