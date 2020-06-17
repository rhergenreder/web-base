<?php

namespace Api\User;

use Api\Parameter\StringType;
use \Api\Request;
use Driver\SQL\Condition\Compare;

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

    $username = $this->getParam('username');
    $email = $this->getParam('email');
    if (!$this->userExists($username, $email) || !$this->success) {
        return false;
    }

    $token = generateRandomString(36);
    $valid_until = (new DateTime())->modify("+48 hour");
    $sql = $this->user->getSQL();
    $res = $sql->insert("UserInvite", array("name", "email","token","valid_until"))
        ->addRow($username, $email, $token,$valid_until)
        ->execute();
    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if($this->success) {
        $request = new SendEmail($this->user);
        $this->success = $request->execute(array(
            "from" => "...", "to" => $email));
        $this->lastError  = $request->getLastError();
    }
    return $this->success;
  }

  private function userExists($username, $email) {
      $sql = $this->user->getSQL();
      $res = $sql->select("User.name", "User.email")
          ->from("User")
          ->where(new Compare("User.name", $username), new Compare("User.email", $email))
          ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success && !empty($res)) {
          $row = $res[0];
          if (strcasecmp($username, $row['name']) === 0) {
              return $this->createError("This username is already taken.");
          } else if (strcasecmp($username, $row['email']) === 0) {
              return $this->createError("This email address is already in use.");
          }
      }

      return $this->success;
    }
}