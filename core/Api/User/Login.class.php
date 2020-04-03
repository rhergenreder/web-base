<?php

namespace Api\User;

use \Api\Request;
use \Api\Parameter\Parameter;
use \Api\Parameter\StringType;
use \Driver\SQL\Condition\Compare;

class Login extends Request {

  private int $startedAt;

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      'username' => new StringType('username', 32),
      'password' => new StringType('password'),
      'stayLoggedIn' => new Parameter('stayLoggedIn', Parameter::TYPE_BOOLEAN, true, true)
    ));
    $this->forbidMethod("GET");
  }

  private function wrongCredentials() {
    $runtime = microtime(true) - $this->startedAt;
    $sleepTime = round(3e6 - $runtime);
    if($sleepTime > 0) usleep($sleepTime);
    return $this->createError(L('Wrong username or password'));
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    if($this->user->isLoggedIn()) {
      $this->lastError = L('You are already logged in');
      $this->success = true;
      return true;
    }

    $this->startedAt = microtime(true);
    $this->success = false;
    $username = $this->getParam('username');
    $password = $this->getParam('password');
    $stayLoggedIn = $this->getParam('stayLoggedIn');

    $sql = $this->user->getSQL();
    $res = $sql->select("User.uid", "User.password", "User.salt")
      ->from("User")
      ->where(new Compare("User.name", $username))
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if($this->success) {
      if(count($res) === 0) {
        return $this->wrongCredentials();
      } else {
        $row = $res[0];
        $salt = $row['salt'];
        $uid = $row['uid'];
        $hash = hash('sha256', $password . $salt);
        if($hash === $row['password']) {
          if(!($this->success = $this->user->createSession($uid, $stayLoggedIn))) {
            return $this->createError("Error creating Session: " . $sql->getLastError());
          } else {
            $this->result['logoutIn'] = $this->user->getSession()->getExpiresSeconds();
            $this->success = true;
          }
        }
        else {
          return $this->wrongCredentials();
        }
      }
    }

    return $this->success;
  }
}