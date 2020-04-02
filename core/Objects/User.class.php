<?php

namespace Objects;

use \External\JWT;
use Driver\SQL\Column\Column;
use Driver\SQL\Condition\Compare;
use Driver\SQL\Condition\CondBool;

class User extends ApiObject {

  private $sql;
  private $configuration;
  private $loggedIn;
  private $session;
  private $uid;
  private $username;
  private $language;

  public function __construct($configuration) {
    session_start();
    $this->configuration = $configuration;
    $this->setLangauge(Language::DEFAULT_LANGUAGE());
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
      $this->sql = \Driver\SQL\SQL::createConnection($databaseConf);
    }
  }

  public function getId() { return $this->uid; }
  public function isLoggedIn() { return $this->loggedIn; }
  public function getUsername() { return $this->username; }
  public function getSQL() { return $this->sql; }
  public function getLanguage() { return $this->language; }
  public function setLangauge($language) { $this->language = $language; $language->load(); }
  public function getSession() { return $this->session; }
  public function getConfiguration() { return $this->configuration; }

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
    return array(
      'uid' => $this->uid,
      'name' => $this->username,
      'language' => $this->language,
      'session' => $this->session,
    );
  }

  private function reset() {
    $this->uid = 0;
    $this->username = '';
    $this->loggedIn = false;
    $this->session = false;
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
      $request = new \Api\SetLanguage($this);
      return $request->execute(array("langCode" => $lang));
    }
  }

  public function sendCookies() {
    if($this->loggedIn) {
      $this->session->sendCookie();
    }

    $this->language->sendCookie();
  }

  public function readData($userId, $sessionId, $sessionUpdate = true) {

    $res = $this->sql->select("User.name", "Language.uid as langId", "Language.code as langCode", "Language.name as langName",
        "Session.data", "Session.stay_logged_in")
        ->from("User")
        ->innerJoin("Session", "Session.user_id", "User.uid")
        ->leftJoin("Language", "User.language_id", "Language.uid")
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
        $this->username = $row['name'];
        $this->uid = $userId;
        $this->session = new Session($this, $sessionId);
        $this->session->setData(json_decode($row["data"]));
        $this->session->stayLoggedIn($row["stay_logged_in"]);
        if($sessionUpdate) $this->session->update();
        $this->loggedIn = true;
        if(!is_null($row['langId'])) {
          $this->setLangauge(Language::newInstance($row['langId'], $row['langCode'], $row['langName']));
        }
      }
    }

    return $success;
  }

  private function parseCookies() {
    if(isset($_COOKIE['session'])
      && is_string($_COOKIE['session'])
      && !empty($_COOKIE['session'])
      && ($jwt = $this->configuration->getJWT())) {
      try {
        $token = $_COOKIE['session'];
        $decoded = (array)JWT::decode($token, $jwt->getKey());
        if(!is_null($decoded)) {
          $userId = (isset($decoded['userId']) ? $decoded['userId'] : NULL);
          $sessionId = (isset($decoded['sessionId']) ? $decoded['sessionId'] : NULL);
          if(!is_null($userId) && !is_null($sessionId)) {
            $this->readData($userId, $sessionId);
          }
        }
      } catch(\Exception $e) {
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

    $res = $this->sql->select("ApiKey.user_id as uid", "User.name as username", "Language.uid as langId", "Language.code as langCode", "Language.name as langName")
      ->from("ApiKey")
      ->innerJoin("User", "ApiKey.user_id", "User.uid")
      ->leftJoin("Language", "User.language_id", "Language.uid")
      ->where(new Compare("ApiKey.api_key", $apiKey))
      ->where(new Compare("valid_until", $this->sql->currentTimestamp(), ">"))
      ->where(new COmpare("ApiKey.active", 1))
      ->execute();

    $success = ($res !== FALSE);
    if($success) {
      if(empty($res)) {
        $success = false;
      } else {
        $row = $res[0];
        $this->uid = $row['uid'];
        $this->username = $row['username'];

        if(!is_null($row['langId'])) {
          $this->setLangauge(Language::newInstance($row['langId'], $row['langCode'], $row['langName']));
        }
      }
    }

    return $success;
  }
}

?>
