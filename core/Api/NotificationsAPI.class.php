<?php

namespace Api {

  use Objects\Context;

  abstract class NotificationsAPI extends Request {
    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }
  }
}

namespace Api\Notifications {

  use Api\NotificationsAPI;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Driver\SQL\Column\Column;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondIn;
  use Driver\SQL\Query\Select;
  use Objects\Context;
  use Objects\DatabaseEntity\Group;
  use Objects\DatabaseEntity\Notification;
  use Objects\DatabaseEntity\User;

  class Create extends NotificationsAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'groupId' => new Parameter('groupId', Parameter::TYPE_INT, true),
        'userId' => new Parameter('userId', Parameter::TYPE_INT, true),
        'title' =>  new StringType('title', 32),
        'message' =>  new StringType('message', 256),
      ));
      $this->isPublic = false;
    }

    private function insertUserNotification($userId, $notificationId): bool {
      $sql = $this->context->getSQL();
      $res = $sql->insert("UserNotification", array("user_id", "notification_id"))
        ->addRow($userId, $notificationId)
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    private function insertGroupNotification($groupId, $notificationId): bool {
      $sql = $this->context->getSQL();
      $res = $sql->insert("GroupNotification", array("group_id", "notification_id"))
        ->addRow($groupId, $notificationId)
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    private function createNotification($title, $message): bool|int {
      $sql = $this->context->getSQL();
      $notification = new Notification();
      $notification->title = $title;
      $notification->message = $message;

      $this->success = ($notification->save($sql) !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        return $notification->getId();
      }

      return $this->success;
    }

    public function _execute(): bool {
      $sql = $this->context->getSQL();
      $userId = $this->getParam("userId");
      $groupId = $this->getParam("groupId");
      $title = $this->getParam("title");
      $message = $this->getParam("message");

      if (is_null($userId) && is_null($groupId)) {
        return $this->createError("Either userId or groupId must be specified.");
      } else if(!is_null($userId) && !is_null($groupId)) {
        return $this->createError("Only one of userId and groupId must be specified.");
      } else if(!is_null($userId)) {
        if (User::exists($sql, $userId)) {
          $id = $this->createNotification($title, $message);
          if ($this->success) {
            return $this->insertUserNotification($userId, $id);
          }
        } else {
          return $this->createError("User not found: $userId");
        }
      } else if(!is_null($groupId)) {
        if (Group::exists($sql, $groupId)) {
          $id = $this->createNotification($title, $message);
          if ($this->success) {
            return $this->insertGroupNotification($groupId, $id);
          }
        } else {
          return $this->createError("Group not found: $groupId");
        }
      }

      return $this->success;
    }
  }

  class Fetch extends NotificationsAPI {

    private array $notifications;
    private array $notificationIds;

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'new' => new Parameter('new', Parameter::TYPE_BOOLEAN, true, true)
      ));
      $this->loginRequired = true;
    }

    private function fetchUserNotifications(): bool {
      $onlyNew = $this->getParam('new');
      $userId = $this->context->getUser()->getId();
      $sql = $this->context->getSQL();
      $query = $sql->select($sql->distinct("Notification.id"), "created_at", "title", "message", "type")
        ->from("Notification")
        ->innerJoin("UserNotification", "UserNotification.notification_id", "Notification.id")
        ->where(new Compare("UserNotification.user_id", $userId))
        ->orderBy("created_at")->descending();

      if ($onlyNew) {
        $query->where(new Compare("UserNotification.seen", false));
      }

      return $this->fetchNotifications($query);
    }

    private function fetchGroupNotifications(): bool {
      $onlyNew = $this->getParam('new');
      $userId = $this->context->getUser()->getId();
      $sql = $this->context->getSQL();
      $query = $sql->select($sql->distinct("Notification.id"), "created_at", "title", "message", "type")
        ->from("Notification")
        ->innerJoin("GroupNotification", "GroupNotification.notification_id", "Notification.id")
        ->innerJoin("UserGroup", "GroupNotification.group_id", "UserGroup.group_id")
        ->where(new Compare("UserGroup.user_id", $userId))
        ->orderBy("created_at")->descending();

      if ($onlyNew) {
        $query->where(new Compare("GroupNotification.seen", false));
      }

      return $this->fetchNotifications($query);
    }

    private function fetchNotifications(Select $query): bool {
      $sql = $this->context->getSQL();
      $res = $query->execute();
      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        foreach($res as $row) {
          $id = $row["id"];
          if (!in_array($id, $this->notificationIds)) {
            $this->notificationIds[] = $id;
            $this->notifications[] = array(
              "id" => $id,
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

    public function _execute(): bool {
      $this->notifications = array();
      $this->notificationIds = array();
      if ($this->fetchUserNotifications() && $this->fetchGroupNotifications()) {
        $this->result["notifications"] = $this->notifications;
      }

      return $this->success;
    }
  }

  class Seen extends NotificationsAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array());
      $this->loginRequired = true;
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      $sql = $this->context->getSQL();
      $res = $sql->update("UserNotification")
        ->set("seen", true)
        ->where(new Compare("user_id", $currentUser->getId()))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $res = $sql->update("GroupNotification")
          ->set("seen", true)
          ->where(new CondIn(new Column("group_id"),
            $sql->select("group_id")
              ->from("UserGroup")
              ->where(new Compare("user_id", $currentUser->getId()))))
          ->execute();

        $this->success = ($res !== FALSE);
        $this->lastError = $sql->getLastError();
      }

      return $this->success;
    }
  }
}