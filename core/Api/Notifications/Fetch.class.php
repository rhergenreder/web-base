<?php

namespace Api\Notifications;

use \Api\Request;
use \Driver\SQL\Condition\Compare;

class Fetch extends Request {

  private $notifications;

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array());
    $this->loginRequired = true;
  }

  private function fetchUserNotifications() {
    $userId = $this->user->getId();
    $sql = $this->user->getSQL();
    $res = $sql->select($sql->distinct("Notification.uid"), "created_at", "title", "message")
      ->from("Notification")
      ->innerJoin("UserNotification", "UserNotification.notification_id", "Notification.uid")
      ->where(new Compare("UserNotification.user_id", $userId))
      ->where(new Compare("UserNotification.seen", false))
      ->orderBy("created_at")->descending()
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if ($this->success) {
      foreach($res as $row) {
        $id = $row["uid"];
        if (!isset($this->notifications[$id])) {
          $this->notifications[$id] = array(
            "uid" => $id,
            "title" => $row["title"],
            "message" => $row["message"],
            "created_at" => $row["created_at"],
          );
        }
      }
    }

    return $this->success;
  }

  private function fetchGroupNotifications() {
    $userId = $this->user->getId();
    $sql = $this->user->getSQL();
    $res = $sql->select($sql->distinct("Notification.uid"), "created_at", "title", "message")
      ->from("Notification")
      ->innerJoin("GroupNotification", "GroupNotification.notification_id", "Notification.uid")
      ->innerJoin("UserGroup", "GroupNotification.group_id", "UserGroup.group_id")
      ->where(new Compare("UserGroup.user_id", $userId))
      ->where(new Compare("GroupNotification.seen", false))
      ->orderBy("created_at")->descending()
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if ($this->success) {
      foreach($res as $row) {
        $id = $row["uid"];
        if (!isset($this->notifications[$id])) {
          $this->notifications[$id] = array(
            "uid" => $id,
            "title" => $row["title"],
            "message" => $row["message"],
            "created_at" => $row["created_at"],
          );
        }
      }
    }

    return $this->success;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $this->notifications = array();
    if ($this->fetchUserNotifications() && $this->fetchGroupNotifications()) {
      $this->result["notifications"] = $this->notifications;
    }

    return $this->success;
  }
};

?>
