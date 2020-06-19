<?php

namespace Api\Routes;

use Api\Parameter\StringType;
use \Api\Request;
use Driver\SQL\Column\Column;
use Driver\SQL\Condition\CondBool;
use Driver\SQL\Condition\Regex;

class Find extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      'request' => new StringType('request', 128, true, '/')
    ));

    $this->isPublic = false;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $request = $this->getParam('request');
    if (!startsWith($request, '/')) {
      $request = "/$request";
    }

    $sql = $this->user->getSQL();

    $res = $sql
      ->select("uid", "request", "action", "target", "extra")
      ->from("Route")
      ->where(new CondBool("active"))
      ->where(new Regex("^$request$", new Column("request")))
      ->limit(1)
      ->execute();

    $this->lastError = $sql->getLastError();
    $this->success = ($res !== FALSE);

    if ($this->success) {
      if (!empty($res)) {
        $row = $res[0];
        $this->result["route"] = array(
          "uid"     => intval($row["uid"]),
          "request" => $row["request"],
          "action"  => $row["action"],
          "target"  => $row["target"],
          "extra"   => $row["extra"]
        );
      } else {
        $this->result["route"] = NULL;
      }
    }

    return $this->success;
  }
}