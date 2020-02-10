<?php

namespace Api;
use \Api\Parameter\Parameter;

class RefreshApiKey extends Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array(
      "id" => new Parameter("id", Parameter::TYPE_INT),
    ));
    $this->loginRequired = true;
  }

  private function apiKeyExists() {
    $id = $this->getParam("id");
    $query = "SELECT * FROM ApiKey WHERE uid = ? AND uidUser = ? AND valid_until > now()";
    $request = new ExecuteSelect($this->user);
    $this->success = $request->execute(array("query" => $query, $id, $this->user->getId()));
    $this->lastError = $request->getLastError();

    if($this->success && count($request->getResult()['rows']) == 0) {
      $this->success = false;
      $this->lastError = "This API-Key does not exist.";
    }

    return $this->success;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $id = $this->getParam("id");
    if(!$this->apiKeyExists())
      return false;

    $query = "UPDATE ApiKey SET valid_until = (SELECT DATE_ADD(now(), INTERVAL 30 DAY)) WHERE uid = ? AND uidUser = ? AND valid_until > now()";
    $request = new ExecuteStatement($this->user);
    $this->success = $request->execute(array("query" => $query, $id, $this->user->getId()));
    $this->lastError = $request->getLastError();

    return $this->success;
  }
};

?>
