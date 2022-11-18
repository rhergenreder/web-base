<?php

namespace Core\API {

  use Core\Driver\SQL\Condition\Compare;
  use Core\Objects\Context;

  abstract class ApiKeyAPI extends Request {

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }

    protected function apiKeyExists(int $id): bool {
      $sql = $this->context->getSQL();
      $res = $sql->select($sql->count())
        ->from("ApiKey")
        ->where(new Compare("id", $id))
        ->where(new Compare("user_id", $this->context->getUser()->getId()))
        ->where(new Compare("valid_until", $sql->currentTimestamp(), ">"))
        ->where(new Compare("active", 1))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if($this->success && $res[0]["count"] === 0) {
        return $this->createError("This API-Key does not exist.");
      }

      return $this->success;
    }
  }
}

namespace Core\API\ApiKey {

  use Core\API\ApiKeyAPI;
  use Core\API\Parameter\Parameter;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Condition\CondAnd;
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

      $apiKey = new ApiKey();
      $apiKey->apiKey = generateRandomString(64);
      $apiKey->validUntil = (new \DateTime())->modify("+30 DAY");
      $apiKey->user = $this->context->getUser();

      $this->success = $apiKey->save($sql);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["api_key"] = $apiKey->jsonSerialize();
      }

      return $this->success;
    }
  }

  class Fetch extends ApiKeyAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "showActiveOnly" => new Parameter("showActiveOnly", Parameter::TYPE_BOOLEAN, true, true)
      ));
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

      $apiKeys = ApiKey::findAll($sql, $condition);
      $this->success = ($apiKeys !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["api_keys"] = array();
        foreach($apiKeys as $apiKey) {
          $this->result["api_keys"][$apiKey->getId()] = $apiKey->jsonSerialize();
        }
      }

      return $this->success;
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
      $id = $this->getParam("id");
      if (!$this->apiKeyExists($id)) {
        return false;
      }

      $validUntil = (new \DateTime)->modify("+30 DAY");
      $sql = $this->context->getSQL();
      $this->success = $sql->update("ApiKey")
        ->set("valid_until", $validUntil)
        ->where(new Compare("id", $id))
        ->where(new Compare("user_id", $this->context->getUser()->getId()))
        ->execute();
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["valid_until"] = $validUntil;
      }

      return $this->success;
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
      $id = $this->getParam("id");
      if (!$this->apiKeyExists($id)) {
        return false;
      }

      $sql = $this->context->getSQL();
      $this->success = $sql->update("ApiKey")
        ->set("active", false)
        ->where(new Compare("id", $id))
        ->where(new Compare("user_id", $this->context->getUser()->getId()))
        ->execute();
      $this->lastError = $sql->getLastError();

      return $this->success;
    }
  }
}