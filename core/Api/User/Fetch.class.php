<?php

namespace Api\User;

use \Api\Request;

class Fetch extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array());
    $this->loginRequired = true;
    // $this->requiredGroup = USER_GROUP_ADMIN;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $sql = $this->user->getSQL();
    $res = $sql->select("User.uid as userId", "User.name", "User.email", "Group.uid as groupId", "Group.name as groupName")
      ->from("User")
      ->leftJoin("UserGroup", "User.uid", "UserGroup.user_id")
      ->leftJoin("Group", "Group.uid", "UserGroup.group_id")
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if($this->success) {
      $this->result["users"] = array();
      foreach($res as $row) {
        $userId = $row["userId"];
        $groupId = $row["groupId"];
        $groupName = $row["groupName"];
        if (!isset($this->result["users"][$userId])) {
          $this->result["users"][$userId] = array(
            "uid" => $userId,
            "name" => $row["name"],
            "email" => $row["email"],
            "groups" => array(),
          );
        }

        if(!is_null($groupId)) {
          $this->result["users"][$userId]["groups"][$groupId] = $groupName;
        }
      }
    }

    return $this->success;
  }
}