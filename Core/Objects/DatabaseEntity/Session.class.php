<?php

namespace Core\Objects\DatabaseEntity;

use DateTime;
use Exception;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\Json;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Attribute\Transient;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;

class Session extends DatabaseEntity {

  # in minutes
  const DURATION = 60 * 60 * 24 * 14;
  #[Transient] private Context $context;

  private User $user;
  private DateTime $expires;
  #[MaxLength(45)] private string $ipAddress;
  #[MaxLength(36)] private string $uuid;
  #[DefaultValue(true)] private bool $active;
  #[MaxLength(64)] private ?string $os;
  #[MaxLength(64)] private ?string $browser;
  #[DefaultValue(true)] public bool $stayLoggedIn;
  #[MaxLength(16)] private string $csrfToken;
  #[Json] private mixed $data;

  public function __construct(Context $context, User $user, ?string $csrfToken = null) {
    parent::__construct();
    $this->context = $context;
    $this->user = $user;
    $this->uuid = uuidv4();
    $this->stayLoggedIn = false;
    $this->csrfToken = $csrfToken ?? generateRandomString(16);
    $this->expires = (new DateTime())->modify(sprintf("+%d second", Session::DURATION));
    $this->active = true;
  }

  public static function init(Context $context, string $sessionUUID): ?Session {
    $sql = $context->getSQL();
    $session = Session::findBy(Session::createBuilder($sql, true)
      ->fetchEntities(true)
      ->whereEq("Session.uuid", $sessionUUID)
      ->whereTrue("Session.active")
      ->whereGt("Session.expires", $sql->now()));

    if (!$session) {
      return null;
    }

    $user = $session->getUser();
    if (!$user->isActive() || !$user->isConfirmed()) {
      return null;
    }

    if (is_array($session->data)) {
      foreach ($session->data as $key => $value) {
        $_SESSION[$key] = $value;
        if ($key === "2faAuthenticated" && $value === true) {
          $tfaToken = $session->getUser()->getTwoFactorToken();
          $tfaToken?->authenticate();
        }
      }
    }

    $session->context = $context;
    return $session;
  }

  public function getUser(): User {
    return $this->user;
  }

  private function updateMetaData() {
    $this->expires = (new \DateTime())->modify(sprintf("+%d minutes", Session::DURATION));
    $this->ipAddress = $this->context->isCLI() ? "127.0.0.1" : $_SERVER['REMOTE_ADDR'];
    try {
      $userAgent = @get_browser($_SERVER['HTTP_USER_AGENT'], true);
      $this->os = $userAgent['platform'] ?? "Unknown";
      $this->browser = $userAgent['parent'] ?? "Unknown";
    } catch (Exception $ex) {
      $this->os = "Unknown";
      $this->browser = "Unknown";
    }
  }

  public function setData(array $data) {
    $this->data = $data;
    foreach ($data as $key => $value) {
      $_SESSION[$key] = $value;
    }
  }

  public function getUUID(): string {
    return $this->uuid;
  }

  public function sendCookie(string $domain) {
    $secure = strcmp(getProtocol(), "https") === 0;
    setcookie('session', $this->uuid, $this->getExpiresTime(), "/", $domain, $secure, true);
  }

  public function getExpiresTime(): int {
    return ($this->stayLoggedIn ? $this->expires->getTimestamp() : 0);
  }

  public function getExpiresSeconds(): int {
    return ($this->stayLoggedIn ? $this->expires->getTimestamp() - time() : -1);
  }

  public function destroy(): bool {
    session_destroy();
    $this->active = false;
    return $this->save($this->context->getSQL(), ["active"]);
  }

  public function update(): bool {
    $this->updateMetaData();

    $this->expires = (new DateTime())->modify(sprintf("+%d second", Session::DURATION));
    $this->data = json_encode($_SESSION ?? []);

    $sql = $this->context->getSQL();
    return $this->user->update($sql) &&
           $this->save($sql, ["expires", "data", "os", "browser"]);
  }

  public function getCsrfToken(): string {
    return $this->csrfToken;
  }
}
