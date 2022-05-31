<?php

namespace Api {

  use Objects\User;

  abstract class NewsAPI extends Request {
    public function __construct(User $user, bool $externalCall = false, array $params = array()) {
      parent::__construct($user, $externalCall, $params);
      $this->loginRequired = true;
    }
  }
}

namespace Api\News {

  use Api\NewsAPI;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Driver\SQL\Condition\Compare;
  use Objects\User;

  class Get extends NewsAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, [
        "since" => new Parameter("since", Parameter::TYPE_DATE_TIME, true, null),
        "limit" => new Parameter("limit", Parameter::TYPE_INT, true, 10)
      ]);
    }

    public function _execute(): bool {
      $sql = $this->user->getSQL();
      $query = $sql->select("News.uid", "title", "text", "publishedAt",
        "User.uid as publisherId", "User.name as publisherName", "User.fullName as publisherFullName")
        ->from("News")
        ->innerJoin("User", "User.uid", "News.publishedBy")
        ->orderBy("publishedAt")
        ->descending();

      $since = $this->getParam("since");
      if ($since) {
        $query->where(new Compare("publishedAt", $since, ">="));
      }

      $limit = $this->getParam("limit");
      if ($limit < 1 || $limit > 30) {
        return $this->createError("Limit must be in range 1-30");
      } else {
        $query->limit($limit);
      }

      $res = $query->execute();
      $this->success = $res !== false;
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["news"] = [];
        foreach ($res as $row) {
          $newsId = intval($row["uid"]);
          $this->result["news"][$newsId] = [
            "id" => $newsId,
            "title" => $row["title"],
            "text" => $row["text"],
            "publishedAt" => $row["publishedAt"],
            "publisher" => [
              "id" => intval($row["publisherId"]),
              "name" => $row["publisherName"],
              "fullName" => $row["publisherFullName"]
            ]
          ];
        }
      }

      return $this->success;
    }
  }

  class Publish extends NewsAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, [
        "title" => new StringType("title", 128),
        "text" => new StringType("text", 1024)
      ]);
    }

    public function _execute(): bool {
      $sql = $this->user->getSQL();
      $title = $this->getParam("title");
      $text  = $this->getParam("text");

      $res = $sql->insert("News", ["title", "text"])
        ->addRow($title, $text)
        ->returning("uid")
        ->execute();

      $this->success = $res !== false;
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["newsId"] = $sql->getLastInsertId();
      }

      return $this->success;
    }
  }

  class Delete extends NewsAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, [
        "id" => new Parameter("id", Parameter::TYPE_INT)
      ]);
    }

    public function _execute(): bool {
      $sql = $this->user->getSQL();
      $id = $this->getParam("id");
      $res = $sql->select("publishedBy")
        ->from("News")
        ->where(new Compare("uid", $id))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      } else if (empty($res) || !is_array($res)) {
        return $this->createError("News Post not found");
      } else if (intval($res[0]["publishedBy"]) !== $this->user->getId() && !$this->user->hasGroup(USER_GROUP_ADMIN)) {
        return $this->createError("You do not have permissions to delete news post of other users.");
      }

      $res = $sql->delete("News")
        ->where(new Compare("uid", $id))
        ->execute();

      $this->success = $res !== false;
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }

  class Edit extends NewsAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, [
        "id" => new Parameter("id", Parameter::TYPE_INT),
        "title" => new StringType("title", 128),
        "text" => new StringType("text", 1024)
      ]);
    }

    public function _execute(): bool {

      $sql = $this->user->getSQL();
      $id = $this->getParam("id");
      $text = $this->getParam("text");
      $title = $this->getParam("title");
      $res = $sql->select("publishedBy")
        ->from("News")
        ->where(new Compare("uid", $id))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      } else if (empty($res) || !is_array($res)) {
        return $this->createError("News Post not found");
      } else if (intval($res[0]["publishedBy"]) !== $this->user->getId() && !$this->user->hasGroup(USER_GROUP_ADMIN)) {
        return $this->createError("You do not have permissions to edit news post of other users.");
      }

      $res = $sql->update("News")
        ->set("title", $title)
        ->set("text", $text)
        ->where(new Compare("uid", $id))
        ->execute();

      $this->success = $res !== false;
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }
}