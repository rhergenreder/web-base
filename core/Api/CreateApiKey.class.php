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
    $query = "INSERT INTO ApiKey (uidUser, api_key, valid_until) VALUES (?,?,(SELECT DATE_ADD(now(), INTERVAL 30 DAY)))";
    $request = new ExecuteStatement($this->user);
    $this->success = $request->execute(array("query" => $query, $this->user->getId(), $apiKey));
    $this->lastError = $request->getLastError();
    $this->result["api_key"] = $apiKey;
    $this->result["valid_until"] = "TODO";
    $this->result["uid"] = $this->user->getSQL()->getLastInsertId();
    return $this->success;
  }
};

?>
