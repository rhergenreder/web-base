<?php

namespace Objects;

use Configuration\Configuration;
use Exception;
use External\JWT;
use Driver\SQL\SQL;
use Driver\SQL\Condition\Compare;
use Driver\SQL\Condition\CondBool;
use Objects\TwoFactor\TwoFactorToken;

// TODO: User::authorize and User::readData have similar function body
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
    $this->connectDb();

    if (!is_cli()) {
      @session_start();
      $this->setLanguage(Language::DEFAULT_LANGUAGE());
      $this->parseCookies();
    }
  }

  public function __destruct() {
    if($this->sql && $this->sql->isConnected()) {
      $this->sql->close();
    }
  }

  private function connectDb() {
    $databaseConf = $this->configuration->getDatabase();
    if($databaseConf) {
      $this->sql = SQL::createConnection($databaseConf);
      if ($this->sql->isConnected()) {
        $settings = $this->configuration->getSettings();
        $settings->loadFromDatabase($this);
      }
    } else {
      $this->sql = null;
    }
  }

  public function getId(): int { return $this->uid; }
  public function isLoggedIn(): bool { return $this->loggedIn; }
  public function getUsername(): string { return $this->username; }
  public function getFullName(): string { return $this->fullName; }
  public function getEmail(): ?string { return $this->email; }
  public function getSQL(): ?SQL { return $this->sql; }
  public function getLanguage(): Language { return $this->language; }
  public function setLanguage(Language $language) { $this->language = $language; $language->load(); }
  public function getSession(): ?Session { return $this->session; }
  public function getConfiguration(): Configuration { return $this->configuration; }
  public function getGroups(): array { return $this->groups; }
  public function hasGroup(int $group): bool { return isset($this->groups[$group]); }
  public function getGPG(): ?GpgKey { return $this->gpgKey; }
  public function getTwoFactorToken(): ?TwoFactorToken { return $this->twoFactorToken; }
  public function getProfilePicture() : ?string { return $this->profilePicture; }

  public function __debugInfo(): array {
    $debugInfo = array(
      'loggedIn' => $this->loggedIn,
      'language' => $this->language->getName(),
    );

    if($this->loggedIn) {
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
    if($this->sql) {
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
  public function readData($userId, $sessionId, bool $sessionUpdate = true): bool {

    $res = $this->sql->select("User.name", "User.email", "User.fullName",
        "User.profilePicture",
        "User.gpg_id", "GpgKey.confirmed as gpg_confirmed", "GpgKey.fingerprint as gpg_fingerprint",
          "GpgKey.expires as gpg_expires", "GpgKey.algorithm as gpg_algorithm",
        "User.2fa_id", "2FA.confirmed as 2fa_confirmed", "2FA.data as 2fa_data", "2FA.type as 2fa_type",
        "Language.uid as langId", "Language.code as langCode", "Language.name as langName",
        "Session.data", "Session.stay_logged_in", "Session.csrf_token", "Group.uid as groupId", "Group.name as groupName")
        ->from("User")
        ->innerJoin("Session", "Session.user_id", "User.uid")
        ->leftJoin("Language", "User.language_id", "Language.uid")
        ->leftJoin("UserGroup", "UserGroup.user_id", "User.uid")
        ->leftJoin("Group", "UserGroup.group_id", "Group.uid")
        ->leftJoin("GpgKey", "User.gpg_id", "GpgKey.uid")
        ->leftJoin("2FA", "User.2fa_id", "2FA.uid")
        ->where(new Compare("User.uid", $userId))
        ->where(new Compare("Session.uid", $sessionId))
        ->where(new Compare("Session.active", true))
        ->where(new CondBool("Session.stay_logged_in"), new Compare("Session.expires", $this->sql->currentTimestamp(), '>'))
        ->execute();

    $success = ($res !== FALSE);
    if($success) {
      if(empty($res)) {
        $success = false;
      } else {
        $row = $res[0];
        $csrfToken = $row["csrf_token"];
        $this->username = $row['name'];
        $this->email = $row["email"];
        $this->fullName = $row["fullName"];
        $this->uid = $userId;
        $this->profilePicture = $row["profilePicture"];

        $this->session = new Session($this, $sessionId, $csrfToken);
        $this->session->setData(json_decode($row["data"] ?? '{}', true));
        $this->session->stayLoggedIn($this->sql->parseBool($row["stay_logged_in"]));
        if ($sessionUpdate) $this->session->update();
        $this->loggedIn = true;

        if (!empty($row["gpg_id"])) {
          $this->gpgKey = new GpgKey($row["gpg_id"], $this->sql->parseBool($row["gpg_confirmed"]),
            $row["gpg_fingerprint"], $row["gpg_algorithm"], $row["gpg_expires"]);
        }

        if (!empty($row["2fa_id"])) {
          $this->twoFactorToken = TwoFactorToken::newInstance($row["2fa_type"], $row["2fa_data"],
            $row["2fa_id"], $this->sql->parseBool($row["2fa_confirmed"]));
        }

        if(!is_null($row['langId'])) {
          $this->setLanguage(Language::newInstance($row['langId'], $row['langCode'], $row['langName']));
        }

        foreach($res as $row) {
          $this->groups[$row["groupId"]] = $row["groupName"];
        }
      }
    }

    return $success;
  }

  private function parseCookies() {
    if (isset($_COOKIE['session']) && is_string($_COOKIE['session']) && !empty($_COOKIE['session'])) {
      try {
        $token = $_COOKIE['session'];
        $settings = $this->configuration->getSettings();
        $decoded = (array)JWT::decode($token, $settings->getJwtSecret());
        if(!is_null($decoded)) {
          $userId = ($decoded['userId'] ?? NULL);
          $sessionId = ($decoded['sessionId'] ?? NULL);
          if(!is_null($userId) && !is_null($sessionId)) {
            $this->readData($userId, $sessionId);
          }
        }
      } catch(Exception $e) {
        // ignored
      }
    }

    if(isset($_GET['lang']) && is_string($_GET["lang"]) && !empty($_GET["lang"])) {
      $this->updateLanguage($_GET['lang']);
    } else if(isset($_COOKIE['lang']) && is_string($_COOKIE["lang"]) && !empty($_COOKIE["lang"])) {
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

  public function authorize($apiKey): bool {

    if ($this->loggedIn) {
      return true;
    }

    $res = $this->sql->select("ApiKey.user_id as uid", "User.name", "User.fullName", "User.email",
      "User.confirmed", "User.profilePicture",
      "User.gpg_id", "GpgKey.fingerprint as gpg_fingerprint", "GpgKey.expires as gpg_expires",
        "GpgKey.confirmed as gpg_confirmed", "GpgKey.algorithm as gpg_algorithm",
      "User.2fa_id", "2FA.confirmed as 2fa_confirmed", "2FA.data as 2fa_data", "2FA.type as 2fa_type",
      "Language.uid as langId", "Language.code as langCode", "Language.name as langName",
      "Group.uid as groupId", "Group.name as groupName")
      ->from("ApiKey")
      ->innerJoin("User", "ApiKey.user_id", "User.uid")
      ->leftJoin("UserGroup", "UserGroup.user_id", "User.uid")
      ->leftJoin("Group", "UserGroup.group_id", "Group.uid")
      ->leftJoin("Language", "User.language_id", "Language.uid")
      ->leftJoin("GpgKey", "User.gpg_id", "GpgKey.uid")
      ->leftJoin("2FA", "User.2fa_id", "2FA.uid")
      ->where(new Compare("ApiKey.api_key", $apiKey))
      ->where(new Compare("valid_until", $this->sql->currentTimestamp(), ">"))
      ->where(new Compare("ApiKey.active", 1))
      ->execute();

    $success = ($res !== FALSE);
    if ($success) {
      if (empty($res) || !is_array($res)) {
        $success = false;
      } else {
        $row = $res[0];
        if (!$this->sql->parseBool($row["confirmed"])) {
          return false;
        }

        $this->uid = $row['uid'];
        $this->username = $row['name'];
        $this->fullName = $row["fullName"];
        $this->email = $row['email'];
        $this->profilePicture = $row["profilePicture"];

        if (!empty($row["gpg_id"])) {
          $this->gpgKey = new GpgKey($row["gpg_id"], $this->sql->parseBool($row["gpg_confirmed"]),
            $row["gpg_fingerprint"], $row["gpg_algorithm"], $row["gpg_expires"]
          );
        }

        if (!empty($row["2fa_id"])) {
          $this->twoFactorToken = TwoFactorToken::newInstance($row["2fa_type"], $row["2fa_data"],
            $row["2fa_id"], $this->sql->parseBool($row["2fa_confirmed"]));
        }

        if(!is_null($row['langId'])) {
          $this->setLanguage(Language::newInstance($row['langId'], $row['langCode'], $row['langName']));
        }

        foreach($res as $row) {
          $this->groups[$row["groupId"]] = $row["groupName"];
        }
      }
    }

    return $success;
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
    if (!isset($_SERVER["HTTP_USER_AGENT"]) || empty($_SERVER["HTTP_USER_AGENT"])) {
      return false;
    }

    return preg_match('/robot|spider|crawler|curl|^$/i', $_SERVER['HTTP_USER_AGENT']) === 1;
  }
}
