<?php

namespace Objects;

use DateTime;
use \Driver\SQL\Condition\Compare;
use Exception;
use External\JWT;

class Session extends ApiObject {

  # in minutes
  const DURATION = 60*24;

  private ?int $sessionId;
  private User $user;
  private int $expires;
  private string $ipAddress;
  private ?string $os;
  private ?string $browser;
  private bool $stayLoggedIn;
  private string $csrfToken;

  public function __construct(User $user, ?int $sessionId, ?string $csrfToken) {
    $this->user = $user;
    $this->sessionId = $sessionId;
    $this->stayLoggedIn = true;
    $this->csrfToken = $csrfToken ?? generateRandomString(16);
  }

  public static function create($user, $stayLoggedIn) {
    $session = new Session($user, null, null);
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
    } catch(Exception $ex) {
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
    $settings = $this->user->getConfiguration()->getSettings();
    $token = array('userId' => $this->user->getId(), 'sessionId' => $this->sessionId);
    $sessionCookie = JWT::encode($token, $settings->getJwtSecret());
    $secure = strcmp(getProtocol(), "https") === 0;
    setcookie('session', $sessionCookie, $this->getExpiresTime(), "/", "", $secure);
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
      'csrf_token' => $this->csrfToken
    );
  }

  public function insert($stayLoggedIn) {
    $this->updateMetaData();
    $sql = $this->user->getSQL();

    $minutes = Session::DURATION;
    $columns = array("expires", "user_id", "ipAddress", "os", "browser", "data", "stay_logged_in", "csrf_token");

    $success = $sql
      ->insert("Session", $columns)
      ->addRow(
        (new DateTime())->modify("+$minutes minute"),
        $this->user->getId(),
        $this->ipAddress,
        $this->os,
        $this->browser,
        json_encode($_SESSION),
        $stayLoggedIn,
        $this->csrfToken)
      ->returning("uid")
      ->execute();

    if($success) {
      $this->sessionId = $this->user->getSQL()->getLastInsertId();
      return true;
    }

    return false;
  }

  public function destroy() {
    return $this->user->getSQL()->update("Session")
      ->set("active", false)
      ->where(new Compare("Session.uid", $this->sessionId))
      ->where(new Compare("Session.user_id", $this->user->getId()))
      ->execute();
  }

  public function update() {
    $this->updateMetaData();
    $minutes = Session::DURATION;

    $sql = $this->user->getSQL();
    return $sql->update("Session")
      ->set("Session.expires", (new DateTime())->modify("+$minutes minute"))
      ->set("Session.ipAddress", $this->ipAddress)
      ->set("Session.os", $this->os)
      ->set("Session.browser", $this->browser)
      ->set("Session.data", json_encode($_SESSION))
      ->set("Session.csrf_token", $this->csrfToken)
      ->where(new Compare("Session.uid", $this->sessionId))
      ->where(new Compare("Session.user_id", $this->user->getId()))
      ->execute();
  }

  public function getCsrfToken(): string {
    return $this->csrfToken;
  }
}
