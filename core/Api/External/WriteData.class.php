<?php

namespace Api\External;
use Api\Parameter\Parameter;
use Api\Parameter\StringType;

class WriteData extends \Api\Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array(
      "url" => new StringType("url", 256),
      "data" => new StringType("data", -1),
      "expires" => new Parameter("expires", Parameter::TYPE_INT, false, 0),
    ));
    $this->isPublic = false;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $url = $this->getParam("url");
    $data = $this->getParam("data");
    $expires = $this->getParam("expires");

    if($expires > 0) {
      $expires = getDateTime(new \DateTime("+${expires} seconds"));
    } else {
      $expires = null;
    }

    $query = "INSERT INTO ExternalSiteCache (url, data, expires) VALUES(?,?,?)
      ON DUPLICATE KEY UPDATE data=?, expires=?";

    $request = new \Api\ExecuteStatement($this->user);
    $this->success = $request->execute(array("query" => $query, $url, $data, $expires, $data, $expires));
    $this->lastError = $request->getLastError();

    return $this->lastError;
  }
}

?>
