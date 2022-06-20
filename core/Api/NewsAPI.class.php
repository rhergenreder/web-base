<?php

namespace Api {

  use Objects\Context;

  abstract class NewsAPI extends Request {
    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
      $this->loginRequired = true;
    }
  }
}

namespace Api\News {

  use Api\NewsAPI;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Driver\SQL\Condition\Compare;
  use Objects\Context;
  use Objects\DatabaseEntity\News;

  class Get extends NewsAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "since" => new Parameter("since", Parameter::TYPE_DATE_TIME, true, null),
        "limit" => new Parameter("limit", Parameter::TYPE_INT, true, 10)
      ]);

      $this->loginRequired = false;
    }

    public function _execute(): bool {
      $since = $this->getParam("since");
      $limit = $this->getParam("limit");
      if ($limit < 1 || $limit > 30) {
        return $this->createError("Limit must be in range 1-30");
      }

      $sql = $this->context->getSQL();
      $newsQuery = News::findAllBuilder($sql)
        ->limit($limit)
        ->orderBy("published_at")
        ->descending()
        ->fetchEntities();

      if ($since) {
        $newsQuery->where(new Compare("published_at", $since, ">="));
      }

      $newsArray = $newsQuery->execute();
      $this->success = $newsArray !== null;
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["news"] = [];
        foreach ($newsArray as $news) {
          $newsId = $news->getId();
          $this->result["news"][$newsId] = $news->jsonSerialize();
        }
      }

      return $this->success;
    }
  }

  class Publish extends NewsAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "title" => new StringType("title", 128),
        "text" => new StringType("text", 1024)
      ]);
      $this->loginRequired = true;
    }

    public function _execute(): bool {

      $news = new News();
      $news->text = $this->getParam("text");
      $news->title = $this->getParam("title");
      $news->publishedBy = $this->context->getUser();

      $sql = $this->context->getSQL();
      $this->success = $news->save($sql);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["newsId"] = $news->getId();
      }

      return $this->success;
    }
  }

  class Delete extends NewsAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "id" => new Parameter("id", Parameter::TYPE_INT)
      ]);
      $this->loginRequired = true;
    }

    public function _execute(): bool {
      $sql = $this->context->getSQL();
      $currentUser = $this->context->getUser();

      $news = News::find($sql, $this->getParam("id"));
      $this->success = ($news !== false);
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      } else if ($news === null) {
        return $this->createError("News Post not found");
      } else if ($news->publishedBy->getId() !== $currentUser->getId() && !$currentUser->hasGroup(USER_GROUP_ADMIN)) {
        return $this->createError("You do not have permissions to delete news post of other users.");
      }

      $this->success = $news->delete($sql);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }

  class Edit extends NewsAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "id" => new Parameter("id", Parameter::TYPE_INT),
        "title" => new StringType("title", 128),
        "text" => new StringType("text", 1024)
      ]);
      $this->loginRequired = true;
    }

    public function _execute(): bool {
      $sql = $this->context->getSQL();
      $currentUser = $this->context->getUser();

      $news = News::find($sql, $this->getParam("id"));
      $this->success = ($news !== false);
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      } else if ($news === null) {
        return $this->createError("News Post not found");
      } else if ($news->publishedBy->getId() !== $currentUser->getId() && !$currentUser->hasGroup(USER_GROUP_ADMIN)) {
        return $this->createError("You do not have permissions to edit news post of other users.");
      }

      $news->text = $this->getParam("text");
      $news->title = $this->getParam("title");
      $this->success = $news->save($sql);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }
}