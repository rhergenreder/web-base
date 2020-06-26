<?php

namespace Api;

use Api\Parameter\Parameter;
use Objects\User;

class SendTestMail extends Request {

  public function __construct(User $user, bool $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      "receiver" => new Parameter("receiver", Parameter::TYPE_EMAIL)
    ));
  }

  public function execute($values = array()) {
    if (!parent::execute($values)) {
      return false;
    }

    $receiver = $this->getParam("receiver");
    $req = new SendMail($this->user);
    $this->success = $req->execute(array(
      "to" => $receiver,
      "subject" => "Test E-Mail",
      "body" => "Hey! If you receive this e-mail, your mail configuration seems to be working."
    ));

    $this->lastError = $req->getLastError();
    return $this->success;
  }

}