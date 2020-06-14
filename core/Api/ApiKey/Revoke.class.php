<?php

namespace Api\ApiKey;

use \Api\Request;
use \Api\Parameter\Parameter;
use \Driver\SQL\Condition\Compare;

class Revoke extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      "id" => new Parameter("id", Parameter::TYPE_INT),
    ));
    $this->loginRequired = true;
    $this->csrfTokenRequired = true;
  }

  private function apiKeyExists() {
    $id = $this->getParam("id");

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
