<?php

namespace Api;

class GetApiKeys extends Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array());
    $this->loginRequired = true;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $query = "SELECT ApiKey.uid, ApiKey.api_key, ApiKey.valid_until
              FROM ApiKey
              WHERE ApiKey.uidUser = ?
              AND ApiKey.valid_until > now()";
    $request = new ExecuteSelect($this->user);
    $this->success = $request->execute(array("query" => $query, $this->user->getId()));
    $this->lastError = $request->getLastError();

    if($this->success) {
      $this->result["api_keys"] = $request->getResult()['rows'];
    }

    return $this->success;
  }
};

?>
