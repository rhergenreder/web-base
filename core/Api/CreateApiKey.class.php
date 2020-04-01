<?php

namespace Api;

class CreateApiKey extends Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array());
    $this->apiKeyAllowed = false;
    $this->loginRequired = true;
  }

  public function execute($values = array()) {

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
      $this->result["api_key"] = $apiKey;
      $this->result["valid_until"] = $validUntil->getTimestamp();
      $this->result["uid"] = $sql->getLastInsertId();
    }
    return $this->success;
  }
};

?>
