<?php

namespace Core\API {

  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\ApiKey;

  abstract class ApiKeyAPI extends Request {

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }

    protected function fetchAPIKey(int $apiKeyId): ApiKey|bool {
      $sql = $this->context->getSQL();
      $apiKey = ApiKey::find($sql, $apiKeyId);
      if ($apiKey === false) {
        return $this->createError("Error fetching API-Key details: " . $sql->getLastError());
      } else if ($apiKey === null) {
        return $this->createError("API-Key does not exit");
      }

      return $apiKey;
    }
  }
}

namespace Core\API\ApiKey {

  use Core\API\ApiKeyAPI;
  use Core\API\Parameter\Parameter;
  use Core\API\Traits\Pagination;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Condition\CondAnd;
  use Core\Driver\SQL\Query\Insert;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\ApiKey;

  class Create extends ApiKeyAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array());
      $this->apiKeyAllowed = false;
      $this->loginRequired = true;
    }

    public function _execute(): bool {
      $sql = $this->context->getSQL();
      $currentUser = $this->context->getUser();

      $apiKey = ApiKey::create($currentUser);
      $this->success = $apiKey->save($sql);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["apiKey"] = $apiKey->jsonSerialize(
          ["id", "validUntil", "token", "active"]
        );
      }

      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [], "Allows users to create new API-Keys", true);
    }
  }

  class Fetch extends ApiKeyAPI {

    use Pagination;

    public function __construct(Context $context, $externalCall = false) {
      $params = $this->getPaginationParameters(["token", "validUntil", "active"]);
      $params["showActiveOnly"] = new Parameter("showActiveOnly", Parameter::TYPE_BOOLEAN, true, true);

      parent::__construct($context, $externalCall, $params);
      $this->loginRequired = true;
    }

    public function _execute(): bool {
      $sql = $this->context->getSQL();

      $condition = new Compare("user_id", $this->context->getUser()->getId());
      if ($this->getParam("showActiveOnly")) {
        $condition = new CondAnd(
          $condition,
          new Compare("valid_until", $sql->currentTimestamp(), ">"),
          new Compare("active", true)
        );
      }

      if (!$this->initPagination($sql, ApiKey::class, $condition)) {
        return false;
      }

      $apiKeys = $this->createPaginationQuery($sql)->execute();
      $this->success = ($apiKeys !== FALSE && $apiKeys !== null);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["apiKeys"] = [];
        foreach($apiKeys as $apiKey) {
          $this->result["apiKeys"][] = $apiKey->jsonSerialize();
        }
      }

      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [], "Allows users to fetch new API-Keys", true);
    }
  }

  class Refresh extends ApiKeyAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "id" => new Parameter("id", Parameter::TYPE_INT),
      ));
      $this->loginRequired = true;
    }

    public function _execute(): bool {
      $apiKey = $this->fetchAPIKey($this->getParam("id"));
      if ($apiKey) {
        $sql = $this->context->getSQL();
        $this->success = $apiKey->refresh($sql, 30) !== false;
        $this->lastError = $sql->getLastError();

        if ($this->success) {
          $this->result["validUntil"] = $apiKey->getValidUntil()->getTimestamp();
        }
      }
      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [], "Allows users to refresh API-Keys", true);
    }
  }

  class Revoke extends ApiKeyAPI {

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "id" => new Parameter("id", Parameter::TYPE_INT),
      ));
      $this->loginRequired = true;
    }

    public function _execute(): bool {
      $apiKey = $this->fetchAPIKey($this->getParam("id"));
      if ($apiKey) {
        $sql = $this->context->getSQL();
        $this->success = $apiKey->revoke($sql);
        $this->lastError = $sql->getLastError();
      }

      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [], "Allows users to revoke API-Keys", true);
    }
  }
}