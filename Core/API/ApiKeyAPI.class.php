<?php

namespace Core\API {

  use Core\Objects\Context;

  abstract class ApiKeyAPI extends Request {

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }
  }
}

namespace Core\API\ApiKey {

  use Core\API\ApiKeyAPI;
  use Core\API\Parameter\Parameter;
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

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [], "Allows users to create new API-Keys");
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

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [], "Allows users to fetch new API-Key");
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
      $sql = $this->context->getSQL();
      $id = $this->getParam("id");
      $apiKey = ApiKey::find($sql, $id);
      if ($apiKey === false) {
        return $this->createError("Error fetching API-Key details: " . $sql->getLastError());
      } else if ($apiKey === null) {
        return $this->createError("API-Key does not exit");
      }

      $this->success = $apiKey->refresh($sql, 30) !== false;
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["validUntil"] = $apiKey->getValidUntil()->getTimestamp();
      }

      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [], "Allows users to refresh API-Key");
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
      $sql = $this->context->getSQL();
      $id = $this->getParam("id");
      $apiKey = ApiKey::find($sql, $id);
      if ($apiKey === false) {
        return $this->createError("Error fetching API-Key details: " . $sql->getLastError());
      } else if ($apiKey === null) {
        return $this->createError("API-Key does not exit");
      }

      $this->success = $apiKey->revoke($sql);
      $this->lastError = $sql->getLastError();

      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [], "Allows users to revoke API-Key");
    }
  }
}