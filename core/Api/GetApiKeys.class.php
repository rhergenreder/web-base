<?php

namespace Api;

use \Driver\SQL\Condition\Compare;

class GetApiKeys extends Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array());
    $this->loginRequired = true;
  }

  public function execute($values = array()) {
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
        $this->result["api_keys"][] = array(
          "uid" => $row["uid"],
          "api_key" => $row["api_key"],
          "valid_until" => (new \DateTime($row["valid_until"]))->getTimestamp(),
        );
      }
    }

    return $this->success;
  }
};

?>
