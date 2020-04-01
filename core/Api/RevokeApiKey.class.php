<?php

namespace Api;

use \Api\Parameter\Parameter;
use \Driver\SQL\Keyword;
use \Driver\SQL\Condition\Compare;

class RevokeApiKey extends Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array(
      "id" => new Parameter("id", Parameter::TYPE_INT),
    ));
    $this->loginRequired = true;
  }

  private function apiKeyExists() {
    $id = $this->getParam("id");

    $sql = $this->user->getSQL();
    $res = $sql->select("COUNT(*)")
      ->from("ApiKey")
      ->where(new Compare("uid", $id))
      ->where(new Compare("user_id", $this->user->getId()))
      ->where(new Compare("valid_until", new Keyword($sql->currentTimestamp()), ">"))
      ->where(new Compare("active", 1))
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if($this->success && $res[0]["COUNT(*)"] === 0) {
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
};

?>
