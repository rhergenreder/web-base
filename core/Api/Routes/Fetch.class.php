<?php

namespace Api\Routes;

use \Api\Request;
use \Driver\SQL\Condition\Compare;

class Fetch extends Request {

  private array $notifications;

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

    $res = $sql
      ->select("uid", "request", "action", "target", "extra", "active")
      ->from("Route")
      ->orderBy("uid")
      ->ascending()
      ->execute();

    $this->lastError = $sql->getLastError();
    $this->success = ($res !== FALSE);

    if ($this->success) {
      $routes = array();
      foreach($res as $row) {
        $routes[] = array(
          "uid"     => intval($row["uid"]),
          "request" => $row["request"],
          "action"  => $row["action"],
          "target"  => $row["target"],
          "extra"   => $row["extra"],
          "active"  => intval($row["active"]),
        );
      }

      $this->result["routes"] = $routes;
    }

    return $this->success;
  }
}