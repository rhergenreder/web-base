<?php

namespace Api;

use Api\Parameter\Parameter;
use Api\Parameter\StringType;

class Login extends Request {

  private $startedAt;

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array(
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

    $query = 'SELECT User.uid, User.password, User.salt FROM User WHERE User.name=?';
    $request = new ExecuteSelect($this->user);
    $this->success = $request->execute(array('query' => $query, $username));
    $this->lastError = $request->getLastError();

    if($this->success) {
      $this->success = false;
      if(count($request->getResult()['rows']) === 0) {
        return $this->wrongCredentials();
        $this->lastError = L('Wrong username or password');
      } else {
        $row = $request->getResult()['rows'][0];
        $salt = $row['salt'];
        $uid = $row['uid'];
        $hash = hash('sha256', $password . $salt);
        if($hash === $row['password']) {
            if(!($this->success = $this->user->createSession($uid, $stayLoggedIn))) {
              return $this->createError("Error creating Session");
            } else {
              $this->result['logoutIn'] = $this->user->getSession()->getExpiresSeconds();
            }
        }
        else {
          return $this->wrongCredentials();
        }
      }
    }

    return $this->success;
  }
};

?>
