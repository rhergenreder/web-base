<?php

namespace Objects;

use DateTime;
use \Driver\SQL\Condition\Compare;
use Driver\SQL\Expression\CurrentTimeStamp;
use Exception;
use External\JWT;

class Session extends ApiObject {

  # in minutes
  const DURATION = 60*60*24*14;

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
    $this->stayLoggedIn = false;
    $this->csrfToken = $csrfToken ?? generateRandomString(16);
  }

  public static function create(User $user, bool $stayLoggedIn = false): ?Session {
    $session = new Session($user, null, null);
    if ($session->insert($stayLoggedIn)) {
      $session->stayLoggedIn = $stayLoggedIn;
      return $session;
    }

    return null;
  }

  private function updateMetaData() {
    $this->expires = time() + Session::DURATION;
    $this->ipAddress = is_cli() ? "127.0.0.1" : $_SERVER['REMOTE_ADDR'];
    try {
      $userAgent = @get_browser($_SERVER['HTTP_USER_AGENT'], true);
      $this->os = $userAgent['platform'] ?? "Unknown";
      $this->browser = $userAgent['parent'] ?? "Unknown";
    } catch(Exception $ex) {
      $this->os = "Unknown";
      $this->browser = "Unknown";
    }
  }

  public function setData(array $data) {
    foreach($data as $key => $value) {
      $_SESSION[$key] = $value;
    }
  }

  public function stayLoggedIn(bool $val) {
    $this->stayLoggedIn = $val;
  }

  public function getCookie(): string {
    $this->updateMetaData();
    $settings = $this->user->getConfiguration()->getSettings();
    $token = ['userId' => $this->user->getId(), 'sessionId' => $this->sessionId];
    return JWT::encode($token, $settings->getJwtSecret());
  }

  public function sendCookie(?string $domain = null) {
    $domain = empty($domain) ? "" : $domain;
    $sessionCookie = $this->getCookie();
    $secure = strcmp(getProtocol(), "https") === 0;
    setcookie('session', $sessionCookie, $this->getExpiresTime(), "/", $domain, $secure, true);
  }

  public function getExpiresTime(): int {
    return ($this->stayLoggedIn ? $this->expires : 0);
  }

  public function getExpiresSeconds(): int {
    return ($this->stayLoggedIn ? $this->expires - time() : -1);
  }

  public function jsonSerialize(): array {
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

  public function insert(bool $stayLoggedIn = false): bool {
    $this->updateMetaData();
    $sql = $this->user->getSQL();

    $minutes = Session::DURATION;
    $data = [
      "expires" => (new DateTime())->modify("+$minutes minute"),
      "user_id" => $this->user->getId(),
      "ipAddress" => $this->ipAddress,
      "os" => $this->os,
      "browser" => $this->browser,
      "data" => json_encode($_SESSION ?? []),
      "stay_logged_in" => $stayLoggedIn,
      "csrf_token" => $this->csrfToken
    ];

    $success = $sql
      ->insert("Session", array_keys($data))
      ->addRow(...array_values($data))
      ->returning("uid")
      ->execute();

    if ($success) {
      $this->sessionId = $this->user->getSQL()->getLastInsertId();
      return true;
    }

    return false;
  }

  public function destroy(): bool {
    session_destroy();
    return $this->user->getSQL()->update("Session")
      ->set("active", false)
      ->where(new Compare("Session.uid", $this->sessionId))
      ->where(new Compare("Session.user_id", $this->user->getId()))
      ->execute();
  }

  public function update(): bool {
    $this->updateMetaData();
    $minutes = Session::DURATION;

    $sql = $this->user->getSQL();
    return
      $sql->update("User")
        ->set("last_online", new CurrentTimeStamp())
        ->where(new Compare("uid", $this->user->getId()))
        ->execute() &&
      $sql->update("Session")
        ->set("Session.expires", (new DateTime())->modify("+$minutes second"))
        ->set("Session.ipAddress", $this->ipAddress)
        ->set("Session.os", $this->os)
        ->set("Session.browser", $this->browser)
        ->set("Session.data", json_encode($_SESSION ?? []))
        ->set("Session.csrf_token", $this->csrfToken)
        ->where(new Compare("Session.uid", $this->sessionId))
        ->where(new Compare("Session.user_id", $this->user->getId()))
        ->execute();
  }

  public function getCsrfToken(): string {
    return $this->csrfToken;
  }
}
