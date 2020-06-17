<?php

namespace Api\User;

use Api\Parameter\StringType;
use \Api\Request;

class Invite extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      'username' => new StringType('username', 32),
      'email' => new StringType('email', 64),
    ));
    $this->csrfTokenRequired = true;
    $this->loginRequired = true;
    $this->requiredGroup = USER_GROUP_ADMIN;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    // TODO:

    return $this->success;
  }
}