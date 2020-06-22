<?php

namespace Api {

use Driver\SQL\Condition\Compare;

abstract class UserAPI extends Request {

  protected function userExists($username, $email) {
    $sql = $this->user->getSQL();
    $res = $sql->select("User.name", "User.email")
      ->from("User")
      ->where(new Compare("User.name", $username), new Compare("User.email", $email))
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if ($this->success && !empty($res)) {
      $row = $res[0];
      if (strcasecmp($username, $row['name']) === 0) {
        return $this->createError("This username is already taken.");
      } else if (strcasecmp($username, $row['email']) === 0) {
        return $this->createError("This email address is already in use.");
      }
    }

    return $this->success;
  }

  protected function insertUser($username, $email, $password) {
    $sql = $this->user->getSQL();
    $salt = generateRandomString(16);
    $hash = $this->hashPassword($password, $salt);
    $res = $sql->insert("User", array("name", "password", "salt", "email"))
      ->addRow($username, $hash, $salt, $email)
      ->returning("uid")
      ->execute();

    $this->lastError = $sql->getLastError();
    $this->success = ($res !== FALSE);

    if ($this->success) {
      return $sql->getLastInsertId();
    }

    return $this->success;
  }

  // TODO: replace this with crypt() in the future
  protected function hashPassword($password, $salt) {
    return hash('sha256', $password . $salt);
  }
}

}

namespace Api\User {

  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\SendMail;
  use Api\UserAPI;
  use DateTime;
  use Driver\SQL\Condition\Compare;

  class Create extends UserAPI {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      'username' => new StringType('username', 32),
      'email' => new StringType('email', 64, true),
      'password' => new StringType('password'),
      'confirmPassword' => new StringType('confirmPassword'),
    ));
    $this->csrfTokenRequired = true;
    $this->loginRequired = true;
    $this->requiredGroup = USER_GROUP_ADMIN;
  }

  public function execute($values = array()) {
    if (!parent::execute($values)) {
      return false;
    }

    $username = $this->getParam('username');
    $email = $this->getParam('email');
    if (!$this->userExists($username, $email) || !$this->success) {
      return false;
    }

    $password = $this->getParam('password');
    $confirmPassword = $this->getParam('confirmPassword');
    if ($password !== $confirmPassword) {
      return $this->createError("The given passwords do not match.");
    }

    return $this->insertUser($username, $email, $password) !== FALSE;
  }
}

class Fetch extends UserAPI {

  const SELECT_SIZE = 10;

  private int $userCount;

  public function __construct($user, $externalCall = false) {

    parent::__construct($user, $externalCall, array(
      'page' => new Parameter('page', Parameter::TYPE_INT, true, 1)
    ));

    $this->loginRequired = true;
    $this->requiredGroup = USER_GROUP_ADMIN;
    $this->userCount = 0;
    $this->csrfTokenRequired = true;
  }

  private function getUserCount() {

    $sql = $this->user->getSQL();
    $res = $sql->select($sql->count())->from("User")->execute();
    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if ($this->success) {
      $this->userCount = $res[0]["count"];
    }

    return $this->success;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $page = $this->getParam("page");
    if($page < 1) {
      return $this->createError("Invalid page count");
    }

    if (!$this->getUserCount()) {
      return false;
    }

    $sql = $this->user->getSQL();
    $res = $sql->select("User.uid as userId", "User.name", "User.email", "User.registered_at",
      "Group.uid as groupId", "Group.name as groupName")
      ->from("User")
      ->leftJoin("UserGroup", "User.uid", "UserGroup.user_id")
      ->leftJoin("Group", "Group.uid", "UserGroup.group_id")
      ->orderBy("User.uid")
      ->ascending()
      ->limit(Fetch::SELECT_SIZE)
      ->offset(($page - 1) * Fetch::SELECT_SIZE)
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if($this->success) {
      $this->result["users"] = array();
      foreach($res as $row) {
        $userId = intval($row["userId"]);
        $groupId = intval($row["groupId"]);
        $groupName = $row["groupName"];
        if (!isset($this->result["users"][$userId])) {
          $this->result["users"][$userId] = array(
            "uid" => $userId,
            "name" => $row["name"],
            "email" => $row["email"],
            "registered_at" => $row["registered_at"],
            "groups" => array(),
          );
        }

        if(!is_null($groupId)) {
          $this->result["users"][$userId]["groups"][$groupId] = $groupName;
        }
      }
      $this->result["pageCount"] = intval(ceil($this->userCount / Fetch::SELECT_SIZE));
      $this->result["totalCount"] = $this->userCount;
    }

    return $this->success;
  }
}

class Info extends UserAPI {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array());
    $this->csrfTokenRequired = true;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    if (!$this->user->isLoggedIn()) {
      $this->result["loggedIn"] = false;
    } else {
      $this->result["loggedIn"] = true;
    }

    $this->result["user"] = $this->user->jsonSerialize();
    return $this->success;
  }
}

class Invite extends UserAPI {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      'username' => new StringType('username', 32),
      'email' => new StringType('email', 64),
    ));
    $this->csrfTokenRequired = true;
    $this->loginRequired = true;
    $this->requiredGroup = USER_GROUP_ADMIN;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $username = $this->getParam('username');
    $email = $this->getParam('email');
    if (!$this->userExists($username, $email)) {
      return false;
    }

    //add to DB
    $token = generateRandomString(36);
    $valid_until = (new DateTime())->modify("+48 hour");
    $sql = $this->user->getSQL();
    $res = $sql->insert("UserInvitation", array("username", "email", "token", "valid_until"))
      ->addRow($username, $email, $token, $valid_until)
      ->execute();
    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    //send validation mail
    if($this->success) {
      $request = new SendMail($this->user);
      $link = "http://localhost/acceptInvitation?token=$token";
      $this->success = $request->execute(array(
          "from" => "webmaster@romanh.de",
          "to" => $email,
          "subject" => "Account Invitation for web-base@localhost",
          "body" =>
            "Hello,<br>
you were invited to create an account on web-base@localhost. Click on the following link to confirm the registration, it is 48h valid from now.             
If the invitation was not intended, you can simply ignore this email.<br><br><a href=\"$link\">$link</a>"
        )
      );
      $this->lastError  = $request->getLastError();
    }
    return $this->success;
  }
}

class Login extends UserAPI {

  private int $startedAt;

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      'username' => new StringType('username', 32),
      'password' => new StringType('password'),
      'stayLoggedIn' => new Parameter('stayLoggedIn', Parameter::TYPE_BOOLEAN, true, true)
    ));
    $this->forbidMethod("GET");
  }

  private function wrongCredentials() {
    $runtime = microtime(true) - $this->startedAt;
    $sleepTime = round(3e6 - $runtime);
    if($sleepTime > 0) usleep($sleepTime);
    return $this->createError(L('Wrong username or password'));
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    if($this->user->isLoggedIn()) {
      $this->lastError = L('You are already logged in');
      $this->success = true;
      return true;
    }

    $this->startedAt = microtime(true);
    $this->success = false;
    $username = $this->getParam('username');
    $password = $this->getParam('password');
    $stayLoggedIn = $this->getParam('stayLoggedIn');

    $sql = $this->user->getSQL();
    $res = $sql->select("User.uid", "User.password", "User.salt")
      ->from("User")
      ->where(new Compare("User.name", $username))
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    if($this->success) {
      if(count($res) === 0) {
        return $this->wrongCredentials();
      } else {
        $row = $res[0];
        $salt = $row['salt'];
        $uid = $row['uid'];
        $hash = $this->hashPassword($password, $salt);
        if($hash === $row['password']) {
          if(!($this->success = $this->user->createSession($uid, $stayLoggedIn))) {
            return $this->createError("Error creating Session: " . $sql->getLastError());
          } else {
            $this->result["loggedIn"] = true;
            $this->result['logoutIn'] = $this->user->getSession()->getExpiresSeconds();
            $this->success = true;
          }
        }
        else {
          return $this->wrongCredentials();
        }
      }
    }

    return $this->success;
  }
}

class Logout extends UserAPI {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall);
    $this->loginRequired = true;
    $this->apiKeyAllowed = false;
    $this->csrfTokenRequired = true;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $this->success = $this->user->logout();
    $this->lastError = $this->user->getSQL()->getLastError();
    return $this->success;
  }
}

class Register extends UserAPI {

  private ?int $userId;
  private string $token;

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      "username" => new StringType("username", 32),
      "email" => new StringType("email", 64),
      "password" => new StringType("password"),
      "confirmPassword" => new StringType("confirmPassword"),
    ));
  }

  private function insertToken() {
    $validUntil = (new DateTime())->modify("+48 hour");
    $sql = $this->user->getSQL();
    $res = $sql->insert("UserToken", array("user_id", "token", "token_type", "valid_until"))
      ->addRow(array($this->userId, $this->token, "confirmation", $validUntil))
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();
    return $this->success;
  }

  public function execute($values = array()) {
    if (!parent::execute($values)) {
      return false;
    }

    if ($this->user->isLoggedIn()) {
      $this->lastError = L('You are already logged in');
      $this->success = false;
      return false;
    }

    $username = $this->getParam("username");
    $email = $this->getParam('email');
    if (!$this->userExists($username, $email)) {
      return false;
    }

    $password = $this->getParam("password");
    $confirmPassword = $this->getParam("confirmPassword");
    if(strcmp($password, $confirmPassword) !== 0) {
      return $this->createError("The given passwords don't match");
    }

    $id = $this->insertUser($username, $email, $password);
    if ($id === FALSE) {
      return false;
    }

    $this->userId = $id;
    $this->token = generateRandomString(36);
    if ($this->insertToken()) {
      return false;
    }

    $request = new SendMail($this->user);
    $link = "http://localhost/confirmEmail?token=$this->token";
    $this->success = $request->execute(array(
        "from" => "webmaster@romanh.de",
        "to" => $email,
        "subject" => "E-Mail Confirmation for web-base@localhost",
        "body" =>
          "Hello,<br>
you recently registered an account on web-base@localhost. Click on the following link to confirm the registration, it is 48h valid from now.             
If the registration was not intended, you can simply ignore this email.<br><br><a href=\"$link\">$link</a>"
      )
    );

    if (!$this->success) {
      $this->lastError = "Your account was registered but the confirmation email could not be sent. " .
                         "Please contact the server administration. Reason: " . $request->getLastError();
    }

    return $this->success;
  }
}

class CheckToken extends  UserAPI{
    public function __construct($user, $externalCall = false) {
        parent::__construct($user, $externalCall, array(
            'token' => new StringType('token', 36),
        ));
    }

    public function execute($values = array()){
        parent::execute($values);

        $token = $this->getParam('token');
        $sql = $this->user->getSQL();
        $res = $sql->select("token_type")->from("UserToken")
            ->where(new Compare("token",$token), new Compare("valid_until", $sql->now(), ">"))
            ->execute();
        $this->lastError = $sql->getLastError();
        $this->success = ($res !== FALSE);

        if($this->success) {
            if(count($res) == 0) {
                $this->lastError = "This token does not exist or is no longer valid";
                $this->success = false;
                return false;
            }
            $this->result["token_type"] = $res[0];
            $this->result["username"] = $this->user->getUsername();
        }
        return $this->success;
    }
}

}