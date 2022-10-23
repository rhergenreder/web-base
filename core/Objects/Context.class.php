<?php

namespace Objects;

use Configuration\Configuration;
use Configuration\Settings;
use Driver\SQL\Condition\Compare;
use Driver\SQL\Condition\CondLike;
use Driver\SQL\Condition\CondOr;
use Driver\SQL\SQL;
use Firebase\JWT\JWT;
use Objects\DatabaseEntity\Language;
use Objects\DatabaseEntity\Session;
use Objects\DatabaseEntity\User;
use Objects\Router\Router;

class Context {

  private ?SQL $sql;
  private ?Session $session;
  private ?User $user;
  private Configuration $configuration;
  private Language $language;
  public ?Router $router;

  public function __construct() {

    $this->sql = null;
    $this->session = null;
    $this->user = null;
    $this->router = null;
    $this->configuration = new Configuration();
    $this->setLanguage(Language::DEFAULT_LANGUAGE());

    if (!$this->isCLI()) {
      @session_start();
    }
  }

  public function __destruct() {
    if ($this->sql && $this->sql->isConnected()) {
      $this->sql->close();
      $this->sql = null;
    }
  }

  public function setLanguage(Language $language) {
    $this->language = $language;
    $this->language->activate();

    if ($this->user && $this->user->language->getId() !== $language->getId()) {
      $this->user->language = $language;
    }
  }

  public function initSQL(): ?SQL {
    $databaseConf = $this->configuration->getDatabase();
    if ($databaseConf) {
      $this->sql = SQL::createConnection($databaseConf);
      if ($this->sql->isConnected()) {
        $settings = $this->configuration->getSettings();
        $settings->loadFromDatabase($this);
        return $this->sql;
      }
    } else {
      $this->sql = null;
    }

    return null;
  }

  public function getSQL(): ?SQL {
    return $this->sql;
  }

  public function getSettings(): Settings {
    return $this->configuration->getSettings();
  }

  public function getUser(): ?User {
    return $this->user;
  }

  public function sendCookies() {
    $domain = $this->getSettings()->getDomain();
    $this->language->sendCookie($domain);
    $this->session?->sendCookie($domain);
    $this->session?->update();
    session_write_close();
  }

  private function loadSession(int $userId, int $sessionId) {
    $this->session = Session::init($this, $userId, $sessionId);
    $this->user = $this->session?->getUser();
  }

  public function parseCookies() {
    if (isset($_COOKIE['session']) && is_string($_COOKIE['session']) && !empty($_COOKIE['session'])) {
      try {
        $token = $_COOKIE['session'];
        $settings = $this->configuration->getSettings();
        $decoded = (array)JWT::decode($token, $settings->getJwtSecretKey());
        if (!is_null($decoded)) {
          $userId = ($decoded['userId'] ?? NULL);
          $sessionId = ($decoded['sessionId'] ?? NULL);
          if (!is_null($userId) && !is_null($sessionId)) {
            $this->loadSession($userId, $sessionId);
          }
        }
      } catch (\Exception $e) {
        // ignored
      }
    }

    // set language by priority: 1. GET parameter, 2. cookie, 3. user's settings
    if (isset($_GET['lang']) && is_string($_GET["lang"]) && !empty($_GET["lang"])) {
      $this->updateLanguage($_GET['lang']);
    } else if (isset($_COOKIE['lang']) && is_string($_COOKIE["lang"]) && !empty($_COOKIE["lang"])) {
      $this->updateLanguage($_COOKIE['lang']);
    } else if ($this->user) {
      $this->setLanguage($this->user->language);
    }
  }

  public function updateLanguage(string $lang): bool {
    if ($this->sql) {
      $language = Language::findBuilder($this->sql)
        ->where(new CondOr(
            new CondLike("name", "%$lang%"), // english
            new Compare("code", $lang), // de_DE
            new CondLike("code", $lang . "_%"))) // de -> de_%
        ->execute();
      if ($language) {
        $this->setLanguage($language);
        return true;
      }
    }

    return false;
  }

  public function processVisit() {
    if (isset($_COOKIE["PHPSESSID"]) && !empty($_COOKIE["PHPSESSID"])) {
      if ($this->isBot()) {
        return;
      }

      $cookie = $_COOKIE["PHPSESSID"];
      $req = new \Api\Visitors\ProcessVisit($this);
      $req->execute(["cookie" => $cookie]);
    }
  }

  private function isBot(): bool {
    if (empty($_SERVER["HTTP_USER_AGENT"])) {
      return false;
    }

    return preg_match('/robot|spider|crawler|curl|^$/i', $_SERVER['HTTP_USER_AGENT']) === 1;
  }

  public function isCLI(): bool {
    return php_sapi_name() === "cli";
  }

  public function getConfig(): Configuration {
    return $this->configuration;
  }

  public function getSession(): ?Session {
    return $this->session;
  }

  public function loadApiKey(string $apiKey): bool {
    $this->user = User::findBuilder($this->sql)
      ->addJoin(new \Driver\SQL\Join("INNER","ApiKey", "ApiKey.user_id", "User.id"))
      ->where(new Compare("ApiKey.api_key", $apiKey))
      ->where(new Compare("valid_until", $this->sql->currentTimestamp(), ">"))
      ->where(new Compare("ApiKey.active", true))
      ->where(new Compare("User.confirmed", true))
      ->fetchEntities()
      ->execute();

    return $this->user !== null;
  }

  public function createSession(int $userId, bool $stayLoggedIn): ?Session {
    $this->user = User::find($this->sql, $userId);
    if ($this->user) {
      $this->session = new Session($this, $this->user);
      $this->session->stayLoggedIn = $stayLoggedIn;
      if ($this->session->update()) {
        return $this->session;
      }
    }

    $this->user = null;
    $this->session = null;
    return null;
  }

  public function getLanguage(): Language {
    return $this->language;
  }
}