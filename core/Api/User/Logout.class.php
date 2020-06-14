<?php

namespace Api\User;

use \Api\Request;

class Logout extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall);
    $this->loginRequired = true;
    $this->apiKeyAllowed = false;
    $this->csrfTokenRequired = true;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $this->success = $this->user->logout();
    $this->lastError = $this->user->getSQL()->getLastError();
    return $this->success;
  }
}