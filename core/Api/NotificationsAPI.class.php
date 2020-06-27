<?php

namespace Api {
  class NotificationsAPI extends Request {

  }
}

namespace Api\Notifications {

  use Api\NotificationsAPI;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondIn;
  use Driver\SQL\Query\Select;
  use Objects\User;

  class Create extends NotificationsAPI {

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

  class Fetch extends NotificationsAPI {

    private array $notifications;
    private array $notificationids;

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'new' => new Parameter('new', Parameter::TYPE_BOOLEAN, true, true)
      ));
      $this->loginRequired = true;
    }

    private function fetchUserNotifications() {
      $onlyNew = $this->getParam('new');
      $userId = $this->user->getId();
      $sql = $this->user->getSQL();
      $query = $sql->select($sql->distinct("Notification.uid"), "created_at", "title", "message", "type")
        ->from("Notification")
        ->innerJoin("UserNotification", "UserNotification.notification_id", "Notification.uid")
        ->where(new Compare("UserNotification.user_id", $userId))
        ->orderBy("created_at")->descending();

      if ($onlyNew) {
        $query->where(new Compare("UserNotification.seen", false));
      }

      return $this->fetchNotifications($query);
    }

    private function fetchGroupNotifications() {
      $onlyNew = $this->getParam('new');
      $userId = $this->user->getId();
      $sql = $this->user->getSQL();
      $query = $sql->select($sql->distinct("Notification.uid"), "created_at", "title", "message", "type")
        ->from("Notification")
        ->innerJoin("GroupNotification", "GroupNotification.notification_id", "Notification.uid")
        ->innerJoin("UserGroup", "GroupNotification.group_id", "UserGroup.group_id")
        ->where(new Compare("UserGroup.user_id", $userId))
        ->orderBy("created_at")->descending();

      if ($onlyNew) {
        $query->where(new Compare("GroupNotification.seen", false));
      }

      return $this->fetchNotifications($query);
    }

    private function fetchNotifications(Select $query) {
      $sql = $this->user->getSQL();
      $res = $query->execute();
      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        foreach($res as $row) {
          $id = $row["uid"];
          if (!in_array($id, $this->notificationids)) {
            $this->notificationids[] = $id;
            $this->notifications[] = array(
              "uid" => $id,
              "title" => $row["title"],
              "message" => $row["message"],
              "created_at" => $row["created_at"],
              "type" => $row["type"]
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
      $this->notificationids = array();
      if ($this->fetchUserNotifications() && $this->fetchGroupNotifications()) {
        $this->result["notifications"] = $this->notifications;
      }

      return $this->success;
    }
  }

  class Seen extends NotificationsAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array());
      $this->loginRequired = true;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $sql = $this->user->getSQL();
      $res = $sql->update("UserNotification")
        ->set("seen", true)
        ->where(new Compare("user_id", $this->user->getId()))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $res = $sql->update("GroupNotification")
          ->set("seen", true)
          ->where(new CondIn("group_id",
            $sql->select("group_id")
              ->from("UserGroup")
              ->where(new Compare("user_id", $this->user->getId()))))
          ->execute();

        $this->success = ($res !== FALSE);
        $this->lastError = $sql->getLastError();
      }

      return $this->success;
    }
  }
}