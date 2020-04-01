<?php

namespace Api;

class Logout extends Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall);
    $this->loginRequired = true;
    $this->apiKeyAllowed = false;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $this->success = $this->user->logout();
    $this->lastError = $this->user->getSQL()->getLastError();
    return $this->success;
  }
};

?>
