<?php

namespace Core\Objects\DatabaseEntity;

use DateTime;
use Exception;
use Firebase\JWT\JWT;
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
    $this->stayLoggedIn = false;
    $this->csrfToken = $csrfToken ?? generateRandomString(16);
    $this->expires = (new DateTime())->modify(sprintf("+%d second", Session::DURATION));
    $this->active = true;
  }

  public static function init(Context $context, int $userId, int $sessionId): ?Session {
    $session = Session::find($context->getSQL(), $sessionId, true, true);
    if (!$session || !$session->active || $session->user->getId() !== $userId) {
      return null;
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
    foreach ($data as $key => $value) {
      $_SESSION[$key] = $value;
    }
  }

  public function getCookie(): string {
    $this->updateMetaData();
    $settings = $this->context->getSettings();
    $token = ['userId' => $this->user->getId(), 'sessionId' => $this->getId()];
    $jwtPublicKey = $settings->getJwtPublicKey();
    return JWT::encode($token, $jwtPublicKey->getKeyMaterial(), $jwtPublicKey->getAlgorithm());
  }

  public function sendCookie(string $domain) {
    $sessionCookie = $this->getCookie();
    $secure = strcmp(getProtocol(), "https") === 0;
    setcookie('session', $sessionCookie, $this->getExpiresTime(), "/", $domain, $secure, true);
  }

  public function getExpiresTime(): int {
    return ($this->stayLoggedIn ? $this->expires->getTimestamp() : 0);
  }

  public function getExpiresSeconds(): int {
    return ($this->stayLoggedIn ? $this->expires->getTimestamp() - time() : -1);
  }

  public function jsonSerialize(): array {
    return array(
      'id' => $this->getId(),
      'active' => $this->active,
      'expires' => $this->expires->getTimestamp(),
      'ipAddress' => $this->ipAddress,
      'os' => $this->os,
      'browser' => $this->browser,
      'csrf_token' => $this->csrfToken,
      'data' => $this->data,
    );
  }

  public function destroy(): bool {
    session_destroy();
    $this->active = false;
    return $this->save($this->context->getSQL());
  }

  public function update(): bool {
    $this->updateMetaData();

    $this->expires = (new DateTime())->modify(sprintf("+%d second", Session::DURATION));
    $this->data = json_encode($_SESSION ?? []);

    $sql = $this->context->getSQL();
    return $this->user->update($sql) &&
           $this->save($sql);
  }

  public function getCsrfToken(): string {
    return $this->csrfToken;
  }
}
