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

  public function __construct($user, $sessionId = NULL) {
    $this->user = $user;
    $this->sessionId = $sessionId;
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

  public function sendCookie() {
    $this->updateMetaData();
    $jwt = $this->user->getConfiguration()->getJwt();
    if($jwt) {
      $token = array('userId' => $this->user->getId(), 'sessionId' => $this->sessionId);
      $sessionCookie = \External\JWT::encode($token, $jwt->getKey());
      $secure = strcmp(getProtocol(), "https") === 0;
      setcookie('session', $sessionCookie, $this->expires, "/", "", $secure);
    }
  }

  public function getExpiresTime() {
    return $this->expires;
  }

  public function getExpiresSeconds() {
    return ($this->expires - time());
  }

  public function jsonSerialize() {
    return array(
      'uid' => $this->sessionId,
      'uidUser' => $this->user->getId(),
      'expires' => $this->expires,
      'ipAddress' => $this->ipAddress,
      'os' => $this->os,
      'browser' => $this->browser,
    );
  }

  public function insert() {
    $this->updateMetaData();
    $query = 'INSERT INTO Session (expires, uidUser, ipAddress, os, browser)
              VALUES (DATE_ADD(NOW(), INTERVAL ? MINUTE),?,?,?,?)';
    $request = new \Api\ExecuteStatement($this->user);

    $success = $request->execute(array(
      'query' => $query,
      Session::DURATION,
      $this->user->getId(),
      $this->ipAddress,
      $this->os,
      $this->browser,
    ));

    if($success) {
      $this->sessionId = $this->user->getSQL()->getLastInsertId();
      return true;
    }

    return false;
  }

  public function destroy() {
    $query = 'DELETE FROM Session WHERE Session.uid=? OR Session.expires<=NOW()';
    $request = new \Api\ExecuteStatement($this->user);
    $success = $request->execute(array('query' => $query, $this->sessionId));
    return $success;
  }

  public function update() {
    $this->updateMetaData();
    $query = 'UPDATE Session
              SET Session.expires=DATE_ADD(NOW(), INTERVAL ? MINUTE), Session.ipAddress=?,
                  Session.os=?, Session.browser=?
              WHERE Session.uid=?';
    $request = new \Api\ExecuteStatement($this->user);
    $success = $request->execute(array(
      'query' => $query,
      Session::DURATION,
      $this->ipAddress,
      $this->os,
      $this->browser,
      $this->sessionId,
    ));

    return $success;
  }
}

?>
