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
    if (!parent::execute($values)) {
      return false;
    }

    $username = $this->getParam('username');
    $email = $this->getParam('email');
    if (!$this->userExists($username, $email) || !$this->success) {
      return false;
    }

    $password = $this->getParam('password');
    $confirmPassword = $this->getParam('confirmPassword');
    if ($password !== $confirmPassword) {
      return $this->createError("The given passwords do not match.");
    }

    $this->success = $this->createUser($username, $email, $password);
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

  private function createUser($username, $email, $password) {
    $sql = $this->user->getSQL();
    $salt = generateRandomString(16);
    $hash = hash('sha256', $password . $salt);
    $res = $sql->insert("User", array("name", "password", "salt", "email"))
      ->addRow($username, $hash, $salt, $email)
      ->execute();

    $this->lastError = $sql->getLastError();
    $this->success = ($res !== FALSE);
    return $this->success;
  }
}