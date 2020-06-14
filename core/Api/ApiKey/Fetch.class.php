<?php

namespace Api\ApiKey;

use \Api\Request;
use DateTime;
use \Driver\SQL\Condition\Compare;
use Exception;

class Fetch extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array());
    $this->loginRequired = true;
    $this->csrfTokenRequired = true;
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