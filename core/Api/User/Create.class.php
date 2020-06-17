<?php

namespace Api\User;

use Api\Parameter\StringType;
use \Api\Request;
use Driver\SQL\Condition\Compare;

class Create extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      'username' => new StringType('username', 32),
      'email' => new StringType('email', 64, true),
      'password' => new StringType('password'),
      'confirmPassword' => new StringType('confirmPassword'),
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

    if(!$this->userExists($username, $email)) {
        return false;
    }

    $password = $this->getParam('password');
    $confirmPassword = $this->getParam('confirmPassword');

    if($password !== $confirmPassword) {
        return false;
    }

    $sql = $this->user->getSQL();
    $this->lastError = $sql->getLastError();

    $this->success = $this->createUser($username, $email, $password);

    return $this->success;
  }

  private function userExists($username, $email){
      $sql = $this->user->getSQL();
      $res = $sql->select("User.uid", "User.password", "User.salt")
          ->from("User")
          ->where(new Compare("User.name", $username), new Compare("User.email",$email))
          ->execute();

      return count($res) !== 0;
  }

  private function createUser($username, $email, $password){
      $sql = $this->user->getSQL();
      $salt = generateRandomString(16);
      $hash = hash('sha256', $password . $salt);
      $res = $sql->insert("User",array(
          'username' => $username,
          'password' => $hash,
          'email' => $email
      ));
      return $res === TRUE;
  }
}