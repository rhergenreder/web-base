<?php

namespace Api\ApiKey;

use \Api\Request;

class Create extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array());
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