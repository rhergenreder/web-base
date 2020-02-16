<?php

namespace Api;
use \Api\Parameter\Parameter;

class RevokeApiKey extends Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array(
      "id" => new Parameter("id", Parameter::TYPE_INT),
    ));
    $this->loginRequired = true;
  }

  private function apiKeyExists() {
    $id = $this->getParam("id");
    $query = "SELECT * FROM ApiKey WHERE uid = ? AND user_id = ? AND valid_until > now()";
    $request = new ExecuteSelect($this->user);
    $this->success = $request->execute(array("query" => $query, $id, $this->user->getId()));
    $this->lastError = $request->getLastError();

    if($this->success && count($request->getResult()['rows']) == 0) {
      $this->success = false;
      $this->lastError = "This API-Key does not exist.";
    }

    return $this->success;
  }

  public function execute($aValues = array()) {
    if(!parent::execute($aValues)) {
      return false;
    }

    $id = $this->getParam("id");
    if(!$this->apiKeyExists())
      return false;

    $query = "DELETE FROM ApiKey WHERE valid_until < now() OR (uid = ? AND user_id = ?)";
    $request = new ExecuteStatement($this->user);
    $this->success = $request->execute(array("query" => $query, $id, $this->user->getId()));
    $this->lastError = $request->getLastError();

    return $this->success;
  }
};

?>
