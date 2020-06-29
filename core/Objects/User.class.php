<?php

namespace Objects;

use Configuration\Configuration;
use DateTime;
use Driver\SQL\Expression\Add;
use Driver\SQL\Strategy\UpdateStrategy;
use Exception;
use External\JWT;
use Driver\SQL\SQL;
use Driver\SQL\Condition\Compare;
use Driver\SQL\Condition\CondBool;

class User extends ApiObject {

  private ?SQL $sql;
  private Configuration $configuration;
  private bool $loggedIn;
  private ?Session $session;
  private int $uid;
  private string $username;
  private ?string $email;
  private Language $language;
  private array $groups;

  public function __construct($configuration) {
    session_start();
    $this->configuration = $configuration;
    $this->setLanguage(Language::DEFAULT_LANGUAGE());
    $this->reset();
    $this->connectDb();
    $this->parseCookies();
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

  public function getId() { return $this->uid; }
  public function isLoggedIn() { return $this->loggedIn; }
  public function getUsername() { return $this->username; }
  public function getEmail() { return $this->email; }
  public function getSQL() { return $this->sql; }
  public function getLanguage() { return $this->language; }
  public function setLanguage(Language $language) { $this->language = $language; $language->load(); }
  public function getSession() { return $this->session; }
  public function getConfiguration() { return $this->configuration; }
  public function getGroups() { return $this->groups; }
  public function hasGroup(int $group) { return isset($this->groups[$group]); }

  public function __debugInfo() {
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

  public function jsonSerialize() {
    if ($this->isLoggedIn()) {
      return array(
        'uid' => $this->uid,
        'name' => $this->username,
        'email' => $this->email,
        'groups' => $this->groups,
        'language' => $this->language->jsonSerialize(),
        'session' => $this->session->jsonSerialize(),
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
    $this->loggedIn = false;
    $this->session = null;
  }

  public function logout() {
    $success = true;
    if($this->loggedIn) {
      $success = $this->session->destroy();
      $this->reset();
    }

    return $success;
  }

  public function updateLanguage($lang) {
    if($this->sql) {
      $request = new \Api\Language\Set($this);
      return $request->execute(array("langCode" => $lang));
    } else {
        return false;
    }
  }

  public function sendCookies() {
    if($this->loggedIn) {
      $this->session->sendCookie();
    }

    $this->language->sendCookie();
    session_write_close();
  }

  public function readData($userId, $sessionId, $sessionUpdate = true) {

    $res = $this->sql->select("User.name", "User.email",
        "Language.uid as langId", "Language.code as langCode", "Language.name as langName",
        "Session.data", "Session.stay_logged_in", "Session.csrf_token", "Group.uid as groupId", "Group.name as groupName")
        ->from("User")
        ->innerJoin("Session", "Session.user_id", "User.uid")
        ->leftJoin("Language", "User.language_id", "Language.uid")
        ->leftJoin("UserGroup", "UserGroup.user_id", "User.uid")
        ->leftJoin("Group", "UserGroup.group_id", "Group.uid")
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
        $this->uid = $userId;
        $this->session = new Session($this, $sessionId, $csrfToken);
        $this->session->setData(json_decode($row["data"] ?? '{}'));
        $this->session->stayLoggedIn($this->sql->parseBool(["stay_logged_in"]));
        if($sessionUpdate) $this->session->update();
        $this->loggedIn = true;

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
    if(isset($_COOKIE['session'])
      && is_string($_COOKIE['session'])
      && !empty($_COOKIE['session'])) {
      try {
        $token = $_COOKIE['session'];
        $settings = $this->configuration->getSettings();
        $decoded = (array)JWT::decode($token, $settings->getJwtSecret());
        if(!is_null($decoded)) {
          $userId = (isset($decoded['userId']) ? $decoded['userId'] : NULL);
          $sessionId = (isset($decoded['sessionId']) ? $decoded['sessionId'] : NULL);
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

  public function createSession($userId, $stayLoggedIn) {
    $this->uid = $userId;
    $this->session = Session::create($this, $stayLoggedIn);
    if($this->session) {
      $this->loggedIn = true;
      return true;
    }

    return false;
  }

  public function authorize($apiKey) {

    if($this->loggedIn)
      return true;

    $res = $this->sql->select("ApiKey.user_id as uid", "User.name", "User.email", "User.confirmed",
      "Language.uid as langId", "Language.code as langCode", "Language.name as langName")
      ->from("ApiKey")
      ->innerJoin("User", "ApiKey.user_id", "User.uid")
      ->leftJoin("Language", "User.language_id", "Language.uid")
      ->where(new Compare("ApiKey.api_key", $apiKey))
      ->where(new Compare("valid_until", $this->sql->currentTimestamp(), ">"))
      ->where(new Compare("ApiKey.active", 1))
      ->execute();

    $success = ($res !== FALSE);
    if($success) {
      if(empty($res)) {
        $success = false;
      } else {
        $row = $res[0];
        if (!$this->sql->parseBool($row["confirmed"])) {
          return false;
        }

        $this->uid = $row['uid'];
        $this->username = $row['name'];
        $this->email = $row['email'];

        if(!is_null($row['langId'])) {
          $this->setLanguage(Language::newInstance($row['langId'], $row['langCode'], $row['langName']));
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
      $month = (new DateTime())->format("Ym");

      $this->sql->insert("Visitor", array("cookie", "month"))
        ->addRow($cookie, $month)
        ->onDuplicateKeyStrategy(new UpdateStrategy(
          array("month", "cookie"),
          array("count" => new Add("Visitor.count", 1))))
        ->execute();
    }
  }

  private function isBot() {
    if (!isset($_SERVER["HTTP_USER_AGENT"]) || empty($_SERVER["HTTP_USER_AGENT"])) {
      return false;
    }

    return preg_match('/robot|spider|crawler|curl|^$/i', $_SERVER['HTTP_USER_AGENT']) === 1;
  }
}
