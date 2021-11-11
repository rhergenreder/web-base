<?php

namespace Api {

  use Driver\SQL\Condition\Compare;

  abstract class ApiKeyAPI extends Request {

    protected function apiKeyExists($id): bool {
      $sql = $this->user->getSQL();
      $res = $sql->select($sql->count())
        ->from("ApiKey")
        ->where(new Compare("uid", $id))
        ->where(new Compare("user_id", $this->user->getId()))
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

namespace Api\ApiKey {

  use Api\ApiKeyAPI;
  use Api\Parameter\Parameter;
  use DateTime;
  use Driver\SQL\Condition\Compare;
  use Exception;

  class Create extends ApiKeyAPI {

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array());
      $this->apiKeyAllowed = false;
      $this->loginRequired = true;
    }

    public function execute($values = array()): bool {

      if(!parent::execute($values)) {
        return false;
      }

      $apiKey = generateRandomString(64);
      $sql = $this->user->getSQL();
      $validUntil = (new \DateTime())->modify("+30 DAY");

      $this->success = $sql->insert("ApiKey", array("user_id", "api_key", "valid_until"))
        ->addRow($this->user->getId(), $apiKey, $validUntil)
        ->returning("uid")
        ->execute();

      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["api_key"] = array(
          "api_key" => $apiKey,
          "valid_until" => $validUntil->getTimestamp(),
          "uid" => $sql->getLastInsertId(),
        );
      } else {
        $this->result["api_key"] = null;
      }
      return $this->success;
    }
  }

  class Fetch extends ApiKeyAPI {

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array());
      $this->loginRequired = true;
    }

    public function execute($values = array()): bool {
      if(!parent::execute($values)) {
        return false;
      }

      $sql = $this->user->getSQL();
      $res = $sql->select("uid", "api_key", "valid_until")
        ->from("ApiKey")
        ->where(new Compare("user_id", $this->user->getId()))
        ->where(new Compare("valid_until", $sql->currentTimestamp(), ">"))
        ->where(new Compare("active", true))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if($this->success) {
        $this->result["api_keys"] = array();
        foreach($res as $row) {
          try {
            $validUntil = (new DateTime($row["valid_until"]))->getTimestamp();
          } catch (Exception $e) {
            $validUntil = $row["valid_until"];
          }

          $this->result["api_keys"][] = array(
            "uid" => intval($row["uid"]),
            "api_key" => $row["api_key"],
            "valid_until" => $validUntil,
          );
        }
      }

      return $this->success;
    }
  }

  class Refresh extends ApiKeyAPI {

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "id" => new Parameter("id", Parameter::TYPE_INT),
      ));
      $this->loginRequired = true;
    }

    public function execute($values = array()): bool {
      if(!parent::execute($values)) {
        return false;
      }

      $id = $this->getParam("id");
      if(!$this->apiKeyExists($id))
        return false;

      $validUntil = (new \DateTime)->modify("+30 DAY");
      $sql = $this->user->getSQL();
      $this->success = $sql->update("ApiKey")
        ->set("valid_until", $validUntil)
        ->where(new Compare("uid", $id))
        ->where(new Compare("user_id", $this->user->getId()))
        ->execute();
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["valid_until"] = $validUntil->getTimestamp();
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

    public function execute($values = array()): bool {
      if(!parent::execute($values)) {
        return false;
      }

      $id = $this->getParam("id");
      if(!$this->apiKeyExists($id))
        return false;

      $sql = $this->user->getSQL();
      $this->success = $sql->update("ApiKey")
        ->set("active", false)
        ->where(new Compare("uid", $id))
        ->where(new Compare("user_id", $this->user->getId()))
        ->execute();
      $this->lastError = $sql->getLastError();

      return $this->success;
    }
  }


}