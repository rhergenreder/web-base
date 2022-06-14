<?php

namespace Objects;

use Configuration\Configuration;
use Driver\SQL\Condition\CondAnd;
use Exception;
use Driver\SQL\SQL;
use Driver\SQL\Condition\Compare;
use Firebase\JWT\JWT;
use Objects\TwoFactor\TwoFactorToken;

class User extends ApiObject {

  private ?SQL $sql;
  private Configuration $configuration;
  private bool $loggedIn;
  private ?Session $session;
  private int $uid;
  private string $username;
  private string $fullName;
  private ?string $email;
  private ?string $profilePicture;
  private Language $language;
  private array $groups;
  private ?GpgKey $gpgKey;
  private ?TwoFactorToken $twoFactorToken;

  public function __construct($configuration) {
    $this->configuration = $configuration;
    $this->reset();
    $this->connectDB();

    if (!is_cli()) {
      @session_start();
      $this->setLanguage(Language::DEFAULT_LANGUAGE());
      $this->parseCookies();
    }
  }

  public function __destruct() {
    if ($this->sql && $this->sql->isConnected()) {
      $this->sql->close();
    }
  }

  public function connectDB(): bool {
    $databaseConf = $this->configuration->getDatabase();
    if ($databaseConf) {
      $this->sql = SQL::createConnection($databaseConf);
      if ($this->sql->isConnected()) {
        $settings = $this->configuration->getSettings();
        $settings->loadFromDatabase($this);
        return true;
      }
    } else {
      $this->sql = null;
    }

    return false;
  }

  public function getId(): int {
    return $this->uid;
  }

  public function isLoggedIn(): bool {
    return $this->loggedIn;
  }

  public function getUsername(): string {
    return $this->username;
  }

  public function getFullName(): string {
    return $this->fullName;
  }

  public function getEmail(): ?string {
    return $this->email;
  }

  public function getSQL(): ?SQL {
    return $this->sql;
  }

  public function getLanguage(): Language {
    return $this->language;
  }

  public function setLanguage(Language $language) {
    $this->language = $language;
    $language->load();
  }

  public function getSession(): ?Session {
    return $this->session;
  }

  public function getConfiguration(): Configuration {
    return $this->configuration;
  }

  public function getGroups(): array {
    return $this->groups;
  }

  public function hasGroup(int $group): bool {
    return isset($this->groups[$group]);
  }

  public function getGPG(): ?GpgKey {
    return $this->gpgKey;
  }

  public function getTwoFactorToken(): ?TwoFactorToken {
    return $this->twoFactorToken;
  }

  public function getProfilePicture(): ?string {
    return $this->profilePicture;
  }

  public function __debugInfo(): array {
    $debugInfo = array(
      'loggedIn' => $this->loggedIn,
      'language' => $this->language->getName(),
    );

    if ($this->loggedIn) {
      $debugInfo['uid'] = $this->uid;
      $debugInfo['username'] = $this->username;
    }

    return $debugInfo;
  }

  public function jsonSerialize(): array {
    if ($this->isLoggedIn()) {
      return array(
        'uid' => $this->uid,
        'name' => $this->username,
        'fullName' => $this->fullName,
        'profilePicture' => $this->profilePicture,
        'email' => $this->email,
        'groups' => $this->groups,
        'language' => $this->language->jsonSerialize(),
        'session' => $this->session->jsonSerialize(),
        "gpg" => ($this->gpgKey ? $this->gpgKey->jsonSerialize() : null),
        "2fa" => ($this->twoFactorToken ? $this->twoFactorToken->jsonSerialize() : null),
      );
    } else {
      return array(
        'language' => $this->language->jsonSerialize(),
      );
    }
  }

  private function reset() {
    $this->uid = 0;
    $this->username = '';
    $this->email = '';
    $this->groups = [];
    $this->loggedIn = false;
    $this->session = null;
    $this->profilePicture = null;
    $this->gpgKey = null;
    $this->twoFactorToken = null;
  }

  public function logout(): bool {
    $success = true;
    if ($this->loggedIn) {
      $success = $this->session->destroy();
      $this->reset();
    }

    return $success;
  }

  public function updateLanguage($lang): bool {
    if ($this->sql) {
      $request = new \Api\Language\Set($this);
      return $request->execute(array("langCode" => $lang));
    } else {
      return false;
    }
  }

  public function sendCookies() {

    $baseUrl = $this->getConfiguration()->getSettings()->getBaseUrl();
    $domain = parse_url($baseUrl, PHP_URL_HOST);

    if ($this->loggedIn) {
      $this->session->sendCookie($domain);
    }

    $this->language->sendCookie($domain);
    session_write_close();
  }

  /**
   * @param $userId user's id
   * @param $sessionId session's id
   * @param bool $sessionUpdate update session information, including session's lifetime and browser information
   * @return bool true, if the data could be loaded
   */
  public function loadSession($userId, $sessionId, bool $sessionUpdate = true): bool {

    $userRow = $this->loadUser("Session", ["Session.data", "Session.stay_logged_in", "Session.csrf_token"], [
      new Compare("User.uid", $userId),
      new Compare("Session.uid", $sessionId),
      new Compare("Session.active", true),
    ]);

    if ($userRow !== false) {
      $this->session = new Session($this, $sessionId, $userRow["csrf_token"]);
      $this->session->setData(json_decode($userRow["data"] ?? '{}', true));
      $this->session->stayLoggedIn($this->sql->parseBool($userRow["stay_logged_in"]));
      if ($sessionUpdate) {
        $this->session->update();
      }
      $this->loggedIn = true;
    }

    return $userRow !== false;
  }

  private function parseCookies() {
    if (isset($_COOKIE['session']) && is_string($_COOKIE['session']) && !empty($_COOKIE['session'])) {
      try {
        $token = $_COOKIE['session'];
        $settings = $this->configuration->getSettings();
        $decoded = (array)JWT::decode($token, $settings->getJwtKey());
        if (!is_null($decoded)) {
          $userId = ($decoded['userId'] ?? NULL);
          $sessionId = ($decoded['sessionId'] ?? NULL);
          if (!is_null($userId) && !is_null($sessionId)) {
            $this->loadSession($userId, $sessionId);
          }
        }
      } catch (Exception $e) {
        // ignored
      }
    }

    if (isset($_GET['lang']) && is_string($_GET["lang"]) && !empty($_GET["lang"])) {
      $this->updateLanguage($_GET['lang']);
    } else if (isset($_COOKIE['lang']) && is_string($_COOKIE["lang"]) && !empty($_COOKIE["lang"])) {
      $this->updateLanguage($_COOKIE['lang']);
    }
  }

  public function createSession(int $userId, bool $stayLoggedIn = false): bool {
    $this->uid = $userId;
    $this->session = Session::create($this, $stayLoggedIn);
    if ($this->session) {
      $this->loggedIn = true;
      return true;
    }

    return false;
  }

  private function loadUser(string $table, array $columns, array $conditions) {
    $userRow = $this->sql->select(
      // User meta
      "User.uid as userId", "User.name", "User.email", "User.fullName", "User.profilePicture", "User.confirmed",

      // GPG
      "User.gpg_id", "GpgKey.confirmed as gpg_confirmed", "GpgKey.fingerprint as gpg_fingerprint",
      "GpgKey.expires as gpg_expires", "GpgKey.algorithm as gpg_algorithm",

      // 2FA
      "User.2fa_id", "2FA.confirmed as 2fa_confirmed", "2FA.data as 2fa_data", "2FA.type as 2fa_type",

      // Language
      "Language.uid as langId", "Language.code as langCode", "Language.name as langName",

      // additional data
      ...$columns)
      ->from("User")
      ->innerJoin("$table", "$table.user_id", "User.uid")
      ->leftJoin("Language", "User.language_id", "Language.uid")
      ->leftJoin("GpgKey", "User.gpg_id", "GpgKey.uid")
      ->leftJoin("2FA", "User.2fa_id", "2FA.uid")
      ->where(new CondAnd(...$conditions))
      ->first()
      ->execute();

    if ($userRow === null || $userRow === false) {
      return false;
    }

    // Meta data
    $userId = $userRow["userId"];
    $this->uid = $userId;
    $this->username = $userRow['name'];
    $this->fullName = $userRow["fullName"];
    $this->email = $userRow['email'];
    $this->profilePicture = $userRow["profilePicture"];

    // GPG
    if (!empty($userRow["gpg_id"])) {
      $this->gpgKey = new GpgKey($userRow["gpg_id"], $this->sql->parseBool($userRow["gpg_confirmed"]),
        $userRow["gpg_fingerprint"], $userRow["gpg_algorithm"], $userRow["gpg_expires"]
      );
    }

    // 2FA
    if (!empty($userRow["2fa_id"])) {
      $this->twoFactorToken = TwoFactorToken::newInstance($userRow["2fa_type"], $userRow["2fa_data"],
        $userRow["2fa_id"], $this->sql->parseBool($userRow["2fa_confirmed"]));
    }

    // Language
    if (!is_null($userRow['langId'])) {
      $this->setLanguage(Language::newInstance($userRow['langId'], $userRow['langCode'], $userRow['langName']));
    }

    // select groups
    $groupRows = $this->sql->select("Group.uid as groupId", "Group.name as groupName")
      ->from("UserGroup")
      ->where(new Compare("UserGroup.user_id", $userId))
      ->innerJoin("Group", "UserGroup.group_id", "Group.uid")
      ->execute();
    if (is_array($groupRows)) {
      foreach ($groupRows as $row) {
        $this->groups[$row["groupId"]] = $row["groupName"];
      }
    }

    return $userRow;
  }

  public function loadApiKey($apiKey): bool {

    if ($this->loggedIn) {
      return true;
    }

    $userRow = $this->loadUser("ApiKey", [], [
      new Compare("ApiKey.api_key", $apiKey),
      new Compare("valid_until", $this->sql->currentTimestamp(), ">"),
      new Compare("ApiKey.active", 1),
    ]);

    // User must be confirmed to use API-Keys
    if ($userRow === false || !$this->sql->parseBool($userRow["confirmed"])) {
      return false;
    }

    return true;
  }

  public function processVisit() {
    if ($this->sql && $this->sql->isConnected() && isset($_COOKIE["PHPSESSID"]) && !empty($_COOKIE["PHPSESSID"])) {
      if ($this->isBot()) {
        return;
      }

      $cookie = $_COOKIE["PHPSESSID"];
      $req = new \Api\Visitors\ProcessVisit($this);
      $req->execute(array("cookie" => $cookie));
    }
  }

  private function isBot(): bool {
    if (empty($_SERVER["HTTP_USER_AGENT"])) {
      return false;
    }

    return preg_match('/robot|spider|crawler|curl|^$/i', $_SERVER['HTTP_USER_AGENT']) === 1;
  }
}
