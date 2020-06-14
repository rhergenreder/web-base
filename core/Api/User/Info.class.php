<?php

namespace Api\User;

use \Api\Request;

class Info extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array());
    $this->csrfTokenRequired = true;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    if (!$this->user->isLoggedIn()) {
      $this->result["loggedIn"] = false;
    } else {
      $this->result["loggedIn"] = true;
    }

    $this->result["user"] = $this->user->jsonSerialize();
    return $this->success;
  }
}