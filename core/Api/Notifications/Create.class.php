<?php

namespace Api\Notifications;

use \Api\Request;
use \Api\Parameter\Parameter;
use \Api\Parameter\StringType;
use \Driver\SQL\Condition\Compare;

class Create extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      'groupId' => new Parameter('groupId', Parameter::TYPE_INT, true),
      'userId' => new Parameter('userId', Parameter::TYPE_INT, true),
      'title' =>  new StringType('title', 32),
      'message' =>  new StringType('message', 256),
    ));
    $this->isPublic = false;
  }

  private function checkUser($userId) {
    $sql = $this->user->getSQL();
    $res = $sql->select($sql->count())
      ->from("User")
      ->where(new Compare("uid", $userId))
      ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

    if ($this->success) {
      if ($res[0]["count"] == 0) {
        $this->success = false;
        $this->lastError = "User not found";
      }
    }

    return $this->success;
  }

  private function insertUserNotification($userId, $notificationId) {
    $sql = $this->user->getSQL();
    $res = $sql->insert("UserNotification", array("user_id", "notification_id"))
      ->addRow($userId, $notificationId)
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();
    return $this->success;
  }

  private function checkGroup($groupId) {
    $sql = $this->user->getSQL();
    $res = $sql->select($sql->count())
      ->from("Group")
      ->where(new Compare("uid", $groupId))
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if ($this->success) {
      if ($res[0]["count"] == 0) {
        $this->success = false;
        $this->lastError = "Group not found";
      }
    }

    return $this->success;
  }

  private function insertGroupNotification($groupId, $notificationId) {
    $sql = $this->user->getSQL();
    $res = $sql->insert("GroupNotification", array("group_id", "notification_id"))
      ->addRow($groupId, $notificationId)
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();
    return $this->success;
  }

  private function createNotification($title, $message) {
    $sql = $this->user->getSQL();
    $res = $sql->insert("Notification", array("title", "message"))
      ->addRow($title, $message)
      ->returning("uid")
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if ($this->success) {
      return $sql->getLastInsertId();
    }

    return $this->success;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $userId = $this->getParam("userId");
    $groupId = $this->getParam("groupId");
    $title = $this->getParam("title");
    $message = $this->getParam("message");

    if (is_null($userId) && is_null($groupId)) {
      return $this->createError("Either userId or groupId must be specified.");
    } else if(!is_null($userId) && !is_null($groupId)) {
      return $this->createError("Only one of userId and groupId must be specified.");
    } else if(!is_null($userId)) {
      if ($this->checkUser($userId)) {
        $id = $this->createNotification($title, $message);
        if ($this->success) {
          return $this->insertUserNotification($userId, $id);
        }
      }
    } else if(!is_null($groupId)) {
      if ($this->checkGroup($groupId)) {
        $id = $this->createNotification($title, $message);
        if ($this->success) {
          return $this->insertGroupNotification($groupId, $id);
        }
      }
    }

    return $this->success;
  }
}