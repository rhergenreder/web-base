<?php

namespace Objects;

class Session extends ApiObject {

  const DURATION = 120;

  private $sessionId;
  private $user;
  private $expires;
  private $ipAddress;
  private $os;
  private $browser;
  private $stayLoggedIn;

  public function __construct($user, $sessionId) {
    $this->user = $user;
    $this->sessionId = $sessionId;
    $this->stayLoggedIn = true;
  }

  public static function create($user, $stayLoggedIn) {
    $session = new Session($user, null);
    if($session->insert($stayLoggedIn)) {
      return $session;
    }

    return null;
  }

  private function updateMetaData() {
    $this->expires = time() + Session::DURATION * 60;
    $this->ipAddress = $_SERVER['REMOTE_ADDR'];
    try {
      $userAgent = @get_browser($_SERVER['HTTP_USER_AGENT'], true);
      $this->os = $userAgent['platform'] ?? "Unknown";
      $this->browser = $userAgent['parent'] ?? "Unknown";
    } catch(\Exception $ex) {
      $this->os = "Unknown";
      $this->browser = "Unknown";
    }
  }

  public function setData($data) {
    foreach($data as $key => $value) {
      $_SESSION[$key] = $value;
    }
  }

  public function stayLoggedIn($val) {
    $this->stayLoggedIn = $val;
  }

  public function sendCookie() {
    $this->updateMetaData();
    $jwt = $this->user->getConfiguration()->getJwt();
    if($jwt) {
      $token = array('userId' => $this->user->getId(), 'sessionId' => $this->sessionId);
      $sessionCookie = \External\JWT::encode($token, $jwt->getKey());
      $secure = strcmp(getProtocol(), "https") === 0;
      setcookie('session', $sessionCookie, $this->getExpiresTime(), "/", "", $secure);
    }
  }

  public function getExpiresTime() {
    return ($this->stayLoggedIn == 0 ? 0 : $this->expires);
  }

  public function getExpiresSeconds() {
    return ($this->stayLoggedIn == 0 ? -1 : $this->expires - time());
  }

  public function jsonSerialize() {
    return array(
      'uid' => $this->sessionId,
      'user_id' => $this->user->getId(),
      'expires' => $this->expires,
      'ipAddress' => $this->ipAddress,
      'os' => $this->os,
      'browser' => $this->browser,
    );
  }

  public function insert($stayLoggedIn) {
    $this->updateMetaData();
    $query = "INSERT INTO Session (expires, user_id, ipAddress, os, browser, data, stay_logged_in)
              VALUES (DATE_ADD(NOW(), INTERVAL ? MINUTE),?,?,?,?,?,?)";
    $request = new \Api\ExecuteStatement($this->user);

    $success = $request->execute(array(
      'query' => $query,
      Session::DURATION,
      $this->user->getId(),
      $this->ipAddress,
      $this->os,
      $this->browser,
      json_encode($_SESSION),
      $stayLoggedIn
    ));

    if($success) {
      $this->sessionId = $this->user->getSQL()->getLastInsertId();
      return true;
    }

    return false;
  }

  public function destroy() {
    $query = 'DELETE FROM Session WHERE Session.uid=? OR (Session.stay_logged_in = 0 AND Session.expires<=NOW())';
    $request = new \Api\ExecuteStatement($this->user);
    $success = $request->execute(array('query' => $query, $this->sessionId));
    return $success;
  }

  public function update() {
    $this->updateMetaData();

    $query = 'UPDATE Session
            SET Session.expires=DATE_ADD(NOW(), INTERVAL ? MINUTE),
                Session.ipAddress=?, Session.os=?, Session.browser=?, Session.data=?
            WHERE Session.uid=?';

    $request = new \Api\ExecuteStatement($this->user);
    $success = $request->execute(array(
      'query' => $query,
      Session::DURATION,
      $this->ipAddress,
      $this->os,
      $this->browser,
      json_encode($_SESSION),
      $this->sessionId,
    ));
    return $success;
  }
}

?>
