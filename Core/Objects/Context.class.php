<?php

namespace Core\Objects;

use Core\Configuration\Configuration;
use Core\Configuration\Settings;
use Core\Driver\SQL\Condition\Compare;
use Core\Driver\SQL\Condition\CondLike;
use Core\Driver\SQL\Condition\CondOr;
use Core\Driver\SQL\Join\InnerJoin;
use Core\Driver\SQL\SQL;
use Core\Objects\DatabaseEntity\Language;
use Core\Objects\DatabaseEntity\Session;
use Core\Objects\DatabaseEntity\User;
use Core\Objects\Router\Router;

class Context {

  private static Context $instance;

  private ?SQL $sql;
  private ?Session $session;
  private ?User $user;
  private Configuration $configuration;
  private Language $language;
  public ?Router $router;

  private function __construct() {

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

  public static function instance(): self {
    if (!isset(self::$instance)) {
      self::$instance = new Context();
    }
    return self::$instance;
  }

  public function __destruct() {
    if ($this->sql && $this->sql->isConnected()) {
      $this->sql->close();
      $this->sql = null;
    }
  }

  public function setLanguage(Language $language): void {
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

  public function sendCookies(): void {
    $domain = getCurrentHostName();
    $this->language->sendCookie($domain);
    $this->session?->sendCookie($domain);
    $this->session?->update();
    session_write_close();
  }

  private function loadSession(string $sessionUUID): void {
    $this->session = Session::init($this, $sessionUUID);
    $this->user = $this->session?->getUser();
  }

  public function parseCookies(): void {

    if ($this->sql) {
      if (isset($_COOKIE['session']) && is_string($_COOKIE['session']) && !empty($_COOKIE['session'])) {
        $this->loadSession($_COOKIE['session']);
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
      $language = Language::findBy(Language::createBuilder($this->sql, true)
        ->where(new CondOr(
            new CondLike("name", "%$lang%"), // english
            new Compare("code", $lang), // de_DE
            new CondLike("code", "{$lang}_%") // de -> de_%
        ))
      );
      if ($language) {
        $this->setLanguage($language);
        return true;
      }
    }

    return false;
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
    $this->user = User::findBy(User::createBuilder($this->sql, true)
      ->addJoin(new InnerJoin("ApiKey", "ApiKey.user_id", "User.id"))
      ->whereEq("ApiKey.api_key", $apiKey)
      ->whereGt("valid_until", $this->sql->currentTimestamp())
      ->whereTrue("ApiKey.active")
      ->whereTrue("User.confirmed")
      ->whereTrue("User.active")
      ->fetchEntities());

    return $this->user !== null;
  }

  public function createSession(User $user, bool $stayLoggedIn): ?Session {
    $this->user = $user;
    $this->session = new Session($this, $this->user);
    $this->session->stayLoggedIn = $stayLoggedIn;
    if ($this->session->update()) {
      return $this->session;
    } else {
      $this->user = null;
      $this->session = null;
      return null;
    }
  }

  public function getLanguage(): Language {
    return $this->language;
  }

  public function invalidateSessions(bool $keepCurrent = false): bool {
    $query = $this->sql->update("Session")
      ->set("active", false)
      ->whereEq("user_id", $this->user->getId());

    if ($keepCurrent && $this->session !== null) {
      $query->whereNeq("id", $this->session->getId());
    }

    return $query->execute();
  }
}