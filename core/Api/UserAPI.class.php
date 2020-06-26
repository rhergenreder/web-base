<?php

namespace Api {

  use Driver\SQL\Condition\Compare;

  abstract class UserAPI extends Request {

    protected function userExists(?string $username, ?string $email) {

      $conditions = array();
      if (!is_null($username) && !empty($username)) {
        $conditions[] = new Compare("User.name", $username);
      }

      if (!is_null($email) && !empty($email)) {
        $conditions[] = new Compare("User.email", $email);
      }

      if (empty($conditions)) {
        return true;
      }

      $sql = $this->user->getSQL();
      $res = $sql->select("User.name", "User.email")
        ->from("User")
        ->where(...$conditions)
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
      $hash = $this->hashPassword($password);
      $res = $sql->insert("User", array("name", "password", "email"))
        ->addRow($username, $hash, $email)
        ->returning("uid")
        ->execute();

      $this->lastError = $sql->getLastError();
      $this->success = ($res !== FALSE);

      if ($this->success) {
        return $sql->getLastInsertId();
      }

      return $this->success;
    }

    protected function hashPassword($password) {
      return password_hash($password, PASSWORD_BCRYPT);
    }

    protected function checkToken($token) {
      $sql = $this->user->getSQL();
      $res = $sql->select("UserToken.token_type", "User.name", "User.email")
        ->from("UserToken")
        ->innerJoin("User", "UserToken.user_id", "User.uid")
        ->where(new Compare("UserToken.token", $token))
        ->where(new Compare("UserToken.valid_until", $sql->now(), ">"))
        ->where(new Compare("UserToken.used", 0))
        ->execute();
      $this->lastError = $sql->getLastError();
      $this->success = ($res !== FALSE);

      if ($this->success && !empty($res)) {
        return $res[0];
      }

      return array();
    }

    protected function getUser($id) {
      $sql = $this->user->getSQL();
      $res = $sql->select("User.uid as userId", "User.name", "User.email", "User.registered_at",
        "Group.uid as groupId", "Group.name as groupName", "Group.color as groupColor")
        ->from("User")
        ->leftJoin("UserGroup", "User.uid", "UserGroup.user_id")
        ->leftJoin("Group", "Group.uid", "UserGroup.group_id")
        ->where(new Compare("User.uid", $id))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      return ($this->success && !empty($res) ? $res : array());
    }

    protected function getMessageTemplate($key) {
      $req = new \Api\Settings\Get($this->user);
      $this->success = $req->execute(array("key" => $key));
      $this->lastError = $req->getLastError();

      if ($this->success) {
        return $req->getResult()["settings"][$key] ?? "{{link}}";
      }

      return $this->success;
    }
  }

}

namespace Api\User {

  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\SendMail;
  use Api\UserAPI;
  use Api\VerifyCaptcha;
  use DateTime;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondIn;
  use Objects\User;

  class Create extends UserAPI {

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'username' => new StringType('username', 32),
        'email' => new Parameter('email', Parameter::TYPE_EMAIL, true, NULL),
        'password' => new StringType('password'),
        'confirmPassword' => new StringType('confirmPassword'),
      ));

      $this->loginRequired = true;
      $this->requiredGroup = array(USER_GROUP_ADMIN);
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $username = $this->getParam('username');
      $email = $this->getParam('email');
      $password = $this->getParam('password');
      $confirmPassword = $this->getParam('confirmPassword');

      if(strlen($username) < 5 || strlen($username) > 32) {
        return $this->createError("The username should be between 5 and 32 characters long");
      } else if(strcmp($password, $confirmPassword) !== 0) {
        return $this->createError("The given passwords do not match");
      } else if(strlen($password) < 6) {
        return $this->createError("The password should be at least 6 characters long");
      }

      if (!$this->userExists($username, $email)) {
        return false;
      }

      // prevent duplicate keys
      $email = (!is_null($email) && empty($email)) ? null : $email;

      $id = $this->insertUser($username, $email, $password);
      if ($this->success) {
        $this->result["userId"] = $id;
      }

      return $this->success;
    }
  }

  class Fetch extends UserAPI {

    private int $userCount;

    public function __construct($user, $externalCall = false) {

      parent::__construct($user, $externalCall, array(
        'page' => new Parameter('page', Parameter::TYPE_INT, true, 1),
        'count' => new Parameter('count', Parameter::TYPE_INT, true, 20)
      ));

      $this->loginRequired = true;
      $this->requiredGroup = array(USER_GROUP_SUPPORT, USER_GROUP_ADMIN);
      $this->userCount = 0;
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

    private function selectIds($page, $count) {
      $sql = $this->user->getSQL();
      $res = $sql->select("User.uid")
        ->from("User")
        ->limit($count)
        ->offset(($page - 1) * $count)
        ->orderBy("User.uid")
        ->ascending()
        ->execute();

      $this->success = ($res !== NULL);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $ids = array();
        foreach($res as $row) $ids[] = $row["uid"];
        return $ids;
      }

      return false;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $page = $this->getParam("page");
      if ($page < 1) {
        return $this->createError("Invalid page count");
      }

      $count = $this->getParam("count");
      if ($count < 1 || $count > 50) {
        return $this->createError("Invalid fetch count");
      }

      if (!$this->getUserCount()) {
        return false;
      }

      $userIds = $this->selectIds($page, $count);
      if ($userIds === false) {
        return false;
      }

      $sql = $this->user->getSQL();
      $res = $sql->select("User.uid as userId", "User.name", "User.email", "User.registered_at",
        "Group.uid as groupId", "Group.name as groupName", "Group.color as groupColor")
        ->from("User")
        ->leftJoin("UserGroup", "User.uid", "UserGroup.user_id")
        ->leftJoin("Group", "Group.uid", "UserGroup.group_id")
        ->where(new CondIn("User.uid", $userIds))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["users"] = array();
        foreach ($res as $row) {
          $userId = intval($row["userId"]);
          $groupId = intval($row["groupId"]);
          $groupName = $row["groupName"];
          $groupColor = $row["groupColor"];
          if (!isset($this->result["users"][$userId])) {
            $this->result["users"][$userId] = array(
              "uid" => $userId,
              "name" => $row["name"],
              "email" => $row["email"],
              "registered_at" => $row["registered_at"],
              "groups" => array(),
            );
          }

          if (!is_null($groupId)) {
            $this->result["users"][$userId]["groups"][$groupId] = array(
              "name" => $groupName,
              "color" => $groupColor
            );
          }
        }
        $this->result["pageCount"] = intval(ceil($this->userCount / $count));
        $this->result["totalCount"] = $this->userCount;
      }

      return $this->success;
    }
  }

  class Get extends UserAPI {

    public function __construct($user, $externalCall = false) {

      parent::__construct($user, $externalCall, array(
        'id' => new Parameter('id', Parameter::TYPE_INT)
      ));

      $this->loginRequired = true;
      $this->requiredGroup = array(USER_GROUP_SUPPORT, USER_GROUP_ADMIN);
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $id = $this->getParam("id");
      $user = $this->getUser($id);

      if ($this->success) {
        if (empty($user)) {
          return $this->createError("User not found");
        } else {
          $this->result["user"] = array(
            "uid" => $user[0]["userId"],
            "name" => $user[0]["name"],
            "email" => $user[0]["email"],
            "registered_at" => $user[0]["registered_at"],
            "groups" => array()
          );

          foreach($user as $row) {
            if (!is_null($row["groupId"])) {
              $this->result["user"]["groups"][$row["groupId"]] = array(
                "name" => $row["groupName"],
                "color" => $row["groupColor"],
              );
            }
          }
        }
      }

      return $this->success;
    }
  }

  class Info extends UserAPI {

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array());
      $this->csrfTokenRequired = false;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
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

      $this->loginRequired = true;
      $this->requiredGroup = array(USER_GROUP_ADMIN);
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
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
      if ($this->success) {

        $settings = $this->user->getConfiguration()->getSettings();
        $baseUrl = htmlspecialchars($settings->getBaseUrl());
        $siteName = htmlspecialchars($settings->getSiteName());
        $body = $this->getMessageTemplate("message_accept_invite");

        if ($this->success) {

          $replacements = array(
            "link" => "$baseUrl/acceptInvite?token=$token",
            "site_name" => $siteName,
            "base_url" => $baseUrl,
            "username" => htmlspecialchars($username)
          );

          foreach($replacements as $key => $value) {
            $body = str_replace("{{{$key}}}", $value, $body);
          }

          $request = new SendMail($this->user);
          $this->success = $request->execute(array(
            "to" => $email,
            "subject" => "[$siteName] Account Invitation",
            "body" => $body
          ));

          $this->lastError = $request->getLastError();
        }

        if (!$this->success) {
          $this->lastError = "The invitation was created but the confirmation email could not be sent. " .
            "Please contact the server administration. Reason: " . $this->lastError;
        }
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
      if ($sleepTime > 0) usleep($sleepTime);
      return $this->createError(L('Wrong username or password'));
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      if ($this->user->isLoggedIn()) {
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
      $res = $sql->select("User.uid", "User.password")
        ->from("User")
        ->where(new Compare("User.name", $username))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if (count($res) === 0) {
          return $this->wrongCredentials();
        } else {
          $row = $res[0];
          $uid = $row['uid'];
          if (password_verify($password, $row['password'])) {
            if (!($this->success = $this->user->createSession($uid, $stayLoggedIn))) {
              return $this->createError("Error creating Session: " . $sql->getLastError());
            } else {
              $this->result["loggedIn"] = true;
              $this->result["logoutIn"] = $this->user->getSession()->getExpiresSeconds();
              $this->result["csrf_token"] = $this->user->getSession()->getCsrfToken();
              $this->success = true;
            }
          } else {
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
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
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

    public function __construct(User $user, bool $externalCall = false) {
      $parameters = array(
        "username" => new StringType("username", 32),
        'email' => new Parameter('email', Parameter::TYPE_EMAIL),
        "password" => new StringType("password"),
        "confirmPassword" => new StringType("confirmPassword"),
      );

      $settings = $user->getConfiguration()->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $parameters["captcha"] = new StringType("captcha");
      }

      parent::__construct($user, $externalCall, $parameters);
    }

    private function insertToken() {
      $validUntil = (new DateTime())->modify("+48 hour");
      $sql = $this->user->getSQL();
      $res = $sql->insert("UserToken", array("user_id", "token", "token_type", "valid_until"))
        ->addRow($this->userId, $this->token, "confirmation", $validUntil)
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    private function checkSettings() {
      $req = new \Api\Settings\Get($this->user);
      $this->success = $req->execute(array("key" => "user_registration_enabled"));
      $this->lastError = $req->getLastError();

      if ($this->success) {
        return ($req->getResult()["user_registration_enabled"] ?? "0") === "1";
      }

      return $this->success;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      if ($this->user->isLoggedIn()) {
        return $this->createError(L('You are already logged in'));
      }

      $registrationAllowed = $this->checkSettings();
      if (!$this->success) {
        return false;
      }

      if(!$registrationAllowed) {
        return $this->createError("User Registration is not enabled.");
      }

      $settings = $this->user->getConfiguration()->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $captcha = $this->getParam("captcha");
        $req = new VerifyCaptcha($this->user);
        if (!$req->execute(array("captcha" => $captcha, "action" => "register"))) {
          return $this->createError($req->getLastError());
        }
      }

      $username = $this->getParam("username");
      $email = $this->getParam('email');
      if (!$this->userExists($username, $email)) {
        return false;
      }

      $password = $this->getParam("password");
      $confirmPassword = $this->getParam("confirmPassword");
      if (strcmp($password, $confirmPassword) !== 0) {
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

      $settings = $this->user->getConfiguration()->getSettings();
      $baseUrl = htmlspecialchars($settings->getBaseUrl());
      $siteName = htmlspecialchars($settings->getSiteName());
      $body = $this->getMessageTemplate("message_confirm_email");

      if ($this->success) {

        $replacements = array(
          "link" => "$baseUrl/confirmEmail?token=$this->token",
          "site_name" => $siteName,
          "base_url" => $baseUrl,
          "username" => htmlspecialchars($username)
        );

        foreach($replacements as $key => $value) {
          $body = str_replace("{{{$key}}}", $value, $body);
        }

        $request = new SendMail($this->user);
        $this->success = $request->execute(array(
            "to" => $email,
            "subject" => "[$siteName] E-Mail Confirmation",
            "body" => $body
        ));
        $this->lastError = $request->getLastError();
      }

      if (!$this->success) {
        $this->lastError = "Your account was registered but the confirmation email could not be sent. " .
          "Please contact the server administration. Reason: " . $this->lastError;
      }

      return $this->success;
    }
  }

  class CheckToken extends UserAPI {
    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'token' => new StringType('token', 36),
      ));
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $token = $this->getParam('token');
      $tokenEntry = $this->checkToken($token);

      if ($this->success) {
        if (!empty($tokenEntry)) {
          $this->result["token"] = array("type" => $tokenEntry["token_type"]);
          $this->result["user"] = array("name" => $tokenEntry["name"], "email" => $tokenEntry["email"]);
        } else {
          return $this->createError("This token does not exist or is no longer valid");
        }
      }
      return $this->success;
    }
  }

  class Edit extends UserAPI {

    public function __construct(User $user, bool $externalCall) {
      parent::__construct($user, $externalCall, array(
        'id' => new Parameter('id', Parameter::TYPE_INT),
        'username' => new StringType('username', 32, true, NULL),
        'email' => new Parameter('email', Parameter::TYPE_EMAIL, true, NULL),
        'password' => new StringType('password', -1, true, NULL),
        'groups' => new Parameter('groups', Parameter::TYPE_ARRAY, true, NULL),
      ));

      $this->requiredGroup = array(USER_GROUP_ADMIN);
      $this->loginRequired = true;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $id = $this->getParam("id");
      $user = $this->getUser($id);

      if ($this->success) {
        if (empty($user)) {
          return $this->createError("User not found");
        }

        $username = $this->getParam("username");
        $email = $this->getParam("email");
        $password = $this->getParam("password");
        $groups = $this->getParam("groups");

        $email = (!is_null($email) && empty($email)) ? null : $email;

        $groupIds = array();
        if (!is_null($groups)) {
          $param = new Parameter('groupId', Parameter::TYPE_INT);

          foreach($groups as $groupId) {
            if (!$param->parseParam($groupId)) {
              $value = print_r($groupId, true);
              return $this->createError("Invalid Type for groupId in parameter groups: '$value' (Required: " . $param->getTypeName() . ")");
            }

            $groupIds[] = $param->value;
          }

          if ($id === $this->user->getId() && !in_array(USER_GROUP_ADMIN, $groupIds)) {
            return $this->createError("Cannot remove Administrator group from own user.");
          }
        }

        // Check for duplicate username, email
        $usernameChanged = !is_null($username) ? strcasecmp($username, $user[0]["name"]) !== 0 : false;
        $emailChanged = !is_null($email) ? strcasecmp($email, $user[0]["email"]) !== 0 : false;
        if($usernameChanged || $emailChanged) {
          if (!$this->userExists($usernameChanged ? $username : NULL, $emailChanged ? $email : NULL)) {
            return false;
          }
        }

        $sql = $this->user->getSQL();
        $query = $sql->update("User");

        if ($usernameChanged) $query->set("name", $username);
        if ($emailChanged) $query->set("email", $email);
        if (!is_null($password)) $query->set("password", $this->hashPassword($password));

        if (!empty($query->getValues())) {
          $query->where(new Compare("User.uid", $id));
          $res = $query->execute();
          $this->lastError = $sql->getLastError();
          $this->success = ($res !== FALSE);
        }

        if ($this->success && !empty($groupIds)) {

          $deleteQuery = $sql->delete("UserGroup")->where(new Compare("user_id", $id));
          $insertQuery = $sql->insert("UserGroup", array("user_id", "group_id"));

          foreach($groupIds as $groupId) {
            $insertQuery->addRow($id, $groupId);
          }

          $this->success = ($deleteQuery->execute() !== FALSE) && ($insertQuery->execute() !== FALSE);
          $this->lastError = $sql->getLastError();
        }
      }

      return $this->success;
    }
  }

  class Delete extends UserAPI {

    public function __construct(User $user, bool $externalCall) {
      parent::__construct($user, $externalCall, array(
        'id' => new Parameter('id', Parameter::TYPE_INT)
      ));

      $this->requiredGroup = array(USER_GROUP_ADMIN);
      $this->loginRequired = true;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $id = $this->getParam("id");
      if ($id === $this->user->getId()) {
        return $this->createError("You cannot delete your own user.");
      }

      $user = $this->getUser($id);
      if ($this->success) {
        if (empty($user)) {
          return $this->createError("User not found");
        } else {
          $sql = $this->user->getSQL();
          $res = $sql->delete("User")->where(new Compare("uid", $id))->execute();
          $this->success = ($res !== FALSE);
          $this->lastError = $sql->getLastError();
        }
      }

      return $this->success;
    }
  }
}