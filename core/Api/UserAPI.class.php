<?php

namespace Api {

  use Driver\SQL\Condition\Compare;

  abstract class UserAPI extends Request {

    protected function checkUserExists(?string $username, ?string $email = null): bool {

      $conditions = array();
      if ($username) {
        $conditions[] = new Compare("User.name", $username);
      }

      if ($email) {
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
        } else if (strcasecmp($email, $row['email']) === 0) {
          return $this->createError("This email address is already in use.");
        }
      }

      return $this->success;
    }

    protected function checkPasswordRequirements($password, $confirmPassword): bool {
      if ((($password === null) !== ($confirmPassword === null)) || strcmp($password, $confirmPassword) !== 0) {
        return $this->createError("The given passwords do not match");
      } else if(strlen($password) < 6) {
        return $this->createError("The password should be at least 6 characters long");
      }

      return true;
    }

    protected function checkUsernameRequirements($username): bool {
      if (strlen($username) < 5 || strlen($username) > 32) {
        return $this->createError("The username should be between 5 and 32 characters long");
      } else if (!preg_match("/[a-zA-Z0-9_\-]+/", $username)) {
        return $this->createError("The username should only contain the following characters: a-z A-Z 0-9 _ -");
      }

      return true;
    }

    protected function checkRequirements($username, $password, $confirmPassword): bool {
      return $this->checkUsernameRequirements($username) &&
        $this->checkPasswordRequirements($password, $confirmPassword);
    }

    protected function insertUser($username, $email, $password, $confirmed, $fullName = "") {
      $sql = $this->user->getSQL();
      $hash = $this->hashPassword($password);
      $res = $sql->insert("User", array("name", "password", "email", "confirmed", "fullName"))
        ->addRow($username, $hash, $email, $confirmed, $fullName ?? "")
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

    protected function getUser($id) {
      $sql = $this->user->getSQL();
      $res = $sql->select("User.uid as userId", "User.name", "User.fullName", "User.email",
        "User.registered_at", "User.confirmed", "User.last_online", "User.profilePicture",
        "User.gpg_id", "GpgKey.confirmed as gpg_confirmed", "GpgKey.fingerprint as gpg_fingerprint",
          "GpgKey.expires as gpg_expires", "GpgKey.algorithm as gpg_algorithm",
        "Group.uid as groupId", "Group.name as groupName", "Group.color as groupColor")
        ->from("User")
        ->leftJoin("UserGroup", "User.uid", "UserGroup.user_id")
        ->leftJoin("Group", "Group.uid", "UserGroup.group_id")
        ->leftJoin("GpgKey", "GpgKey.uid", "User.gpg_id")
        ->where(new Compare("User.uid", $id))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      return ($this->success && !empty($res) ? $res : array());
    }

    protected function invalidateToken($token) {
      $this->user->getSQL()
        ->update("UserToken")
        ->set("used", true)
        ->where(new Compare("token", $token))
        ->execute();
    }

    protected function insertToken(int $userId, string $token, string $tokenType, int $duration): bool {
      $validUntil = (new \DateTime())->modify("+$duration hour");
      $sql = $this->user->getSQL();
      $res = $sql->insert("UserToken", array("user_id", "token", "token_type", "valid_until"))
        ->addRow($userId, $token, $tokenType, $validUntil)
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    protected function formatDuration(int $count, string $string): string {
      if ($count === 1) {
        return $string;
      } else {
        return "the next $count ${string}s";
      }
    }
  }

}

namespace Api\User {

  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\Template\Render;
  use Api\UserAPI;
  use Api\VerifyCaptcha;
  use DateTime;
  use Driver\SQL\Column\Column;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondBool;
  use Driver\SQL\Condition\CondIn;
  use Driver\SQL\Condition\CondNot;
  use Driver\SQL\Expression\JsonArrayAgg;
  use ImagickException;
  use Objects\GpgKey;
  use Objects\TwoFactor\KeyBasedTwoFactorToken;
  use Objects\TwoFactor\TwoFactorToken;
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
    }

    public function _execute(): bool {

      $username = $this->getParam('username');
      $email = $this->getParam('email');
      $password = $this->getParam('password');
      $confirmPassword = $this->getParam('confirmPassword');

      if (!$this->checkRequirements($username, $password, $confirmPassword)) {
        return false;
      }

      if (!$this->checkUserExists($username, $email)) {
        return false;
      }

      // prevent duplicate keys
      $email = (!is_null($email) && empty($email)) ? null : $email;

      $id = $this->insertUser($username, $email, $password, true);
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
    }

    private function getUserCount(): bool {

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

      if ($this->success && is_array($res)) {
        return array_map(function ($row) {
            return intval($row["uid"]);
          }, $res);
      }

      return false;
    }

    public function _execute(): bool {

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
      $res = $sql->select("User.uid as userId", "User.name", "User.email", "User.registered_at", "User.confirmed",
        "User.profilePicture", "User.fullName", "Group.uid as groupId", "User.last_online",
        "Group.name as groupName", "Group.color as groupColor")
        ->from("User")
        ->leftJoin("UserGroup", "User.uid", "UserGroup.user_id")
        ->leftJoin("Group", "Group.uid", "UserGroup.group_id")
        ->where(new CondIn(new Column("User.uid"), $userIds))
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

          $fullInfo = ($userId === $this->user->getId()) ||
            ($this->user->hasGroup(USER_GROUP_ADMIN) || $this->user->hasGroup(USER_GROUP_SUPPORT));

          if (!isset($this->result["users"][$userId])) {
            $user = array(
              "uid" => $userId,
              "name" => $row["name"],
              "fullName" => $row["fullName"],
              "profilePicture" => $row["profilePicture"],
              "email" => $row["email"],
              "confirmed" => $sql->parseBool($row["confirmed"]),
              "groups" => array(),
            );

            if ($fullInfo) {
              $user["registered_at"] = $row["registered_at"];
              $user["last_online"] = $row["last_online"];
            } else if (!$sql->parseBool($row["confirmed"])) {
              continue;
            }

            $this->result["users"][$userId] = $user;
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
    }

    public function _execute(): bool {

      $sql = $this->user->getSQL();
      $userId = $this->getParam("id");
      $user = $this->getUser($userId);
      if ($this->success) {
        if (empty($user)) {
          return $this->createError("User not found");
        } else {

          $gpgFingerprint = null;
          if ($user[0]["gpg_id"] && $sql->parseBool($user[0]["gpg_confirmed"])) {
            $gpgFingerprint = $user[0]["gpg_fingerprint"];
          }

          $queriedUser = array(
            "uid" => $userId,
            "name" => $user[0]["name"],
            "fullName" => $user[0]["fullName"],
            "email" => $user[0]["email"],
            "registered_at" => $user[0]["registered_at"],
            "last_online" => $user[0]["last_online"],
            "profilePicture" => $user[0]["profilePicture"],
            "confirmed" => $sql->parseBool($user["0"]["confirmed"]),
            "groups" => array(),
            "gpgFingerprint" => $gpgFingerprint,
          );

          foreach($user as $row) {
            if (!is_null($row["groupId"])) {
              $queriedUser["groups"][$row["groupId"]] = array(
                "name" => $row["groupName"],
                "color" => $row["groupColor"],
              );
            }
          }

          // either we are querying own info or we are support / admin
          $canView = ($userId === $this->user->getId()) ||
                ($this->user->hasGroup(USER_GROUP_ADMIN) ||
                $this->user->hasGroup(USER_GROUP_SUPPORT));

          // full info only when we have administrative privileges, or we are querying ourselves
          $fullInfo = ($userId === $this->user->getId()) ||
            ($this->user->hasGroup(USER_GROUP_ADMIN) || $this->user->hasGroup(USER_GROUP_SUPPORT));

          if (!$canView) {

            // check if user posted something publicly
            $res = $sql->select(new JsonArrayAgg(new Column("publishedBy"), "publisherIds"))
              ->from("News")
              ->execute();
            $this->success = ($res !== false);
            $this->lastError = $sql->getLastError();
            if (!$this->success ) {
              return false;
            } else {
              $canView = in_array($userId, json_decode($res[0]["publisherIds"], true));
            }
          }

          if (!$canView) {
            return $this->createError("No permissions to access this user");
          }

          if (!$fullInfo) {
            if (!$queriedUser["confirmed"]) {
              return $this->createError("No permissions to access this user");
            }
            unset($queriedUser["registered_at"]);
            unset($queriedUser["confirmed"]);
            unset($queriedUser["last_online"]);
          }

          $this->result["user"] = $queriedUser;
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

    public function _execute(): bool {

      if (!$this->user->isLoggedIn()) {
        $this->result["loggedIn"] = false;
      } else {
        $this->result["loggedIn"] = true;
        $userGroups = array_keys($this->user->getGroups());
        $sql = $this->user->getSQL();
        $res = $sql->select("method", "groups")
          ->from("ApiPermission")
          ->execute();

        $permissions = [];
        if (is_array($res)) {
          foreach ($res as $row) {
            $requiredGroups = json_decode($row["groups"], true);
            if (empty($requiredGroups) || !empty(array_intersect($requiredGroups, $userGroups))) {
              $permissions[] = $row["method"];
            }
          }
        }

        $this->result["permissions"] = $permissions;
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
    }

    public function _execute(): bool {

      $username = $this->getParam('username');
      $email = $this->getParam('email');
      if (!$this->checkUserExists($username, $email)) {
        return false;
      }

      // Create user
      $id = $this->insertUser($username, $email, "", false);
      if (!$this->success) {
        return false;
      }

      // Create Token
      $token = generateRandomString(36);
      $validDays = 7;
      $valid_until = (new DateTime())->modify("+$validDays day");
      $sql = $this->user->getSQL();
      $res = $sql->insert("UserToken", array("user_id", "token", "token_type", "valid_until"))
        ->addRow($id, $token, "invite", $valid_until)
        ->execute();
      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      //send validation mail
      if ($this->success) {

        $settings = $this->user->getConfiguration()->getSettings();
        $baseUrl = $settings->getBaseUrl();
        $siteName = $settings->getSiteName();

        $req = new Render($this->user);
        $this->success = $req->execute([
          "file" => "mail/accept_invite.twig",
          "parameters" => [
            "link" => "$baseUrl/acceptInvite?token=$token",
            "site_name" => $siteName,
            "base_url" => $baseUrl,
            "username" => $username,
            "valid_time" => $this->formatDuration($validDays, "day")
          ]
        ]);
        $this->lastError = $req->getLastError();

        if ($this->success) {
          $messageBody = $req->getResult()["html"];
          $request = new \Api\Mail\Send($this->user);
          $this->success = $request->execute(array(
            "to" => $email,
            "subject" => "[$siteName] Account Invitation",
            "body" => $messageBody
          ));

          $this->lastError = $request->getLastError();
        }

        if (!$this->success) {
          $this->logger->error("Could not deliver email to=$email type=invite reason=" . $this->lastError);
          $this->lastError = "The invitation was created but the confirmation email could not be sent. " .
            "Please contact the server administration. This issue has been automatically logged. Reason: " . $this->lastError;
        }
      }

      $this->logger->info("Created new user with uid=$id");
      return $this->success;
    }
  }

  class AcceptInvite extends UserAPI {
    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'token' => new StringType('token', 36),
        'password' => new StringType('password'),
        'confirmPassword' => new StringType('confirmPassword'),
      ));
      $this->csrfTokenRequired = false;
    }

    private function updateUser($uid, $password): bool {
      $sql = $this->user->getSQL();
      $res = $sql->update("User")
        ->set("password", $this->hashPassword($password))
        ->set("confirmed", true)
        ->where(new Compare("uid", $uid))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    public function _execute(): bool {

      if ($this->user->isLoggedIn()) {
        return $this->createError("You are already logged in.");
      }

      $token = $this->getParam("token");
      $password = $this->getParam("password");
      $confirmPassword = $this->getParam("confirmPassword");

      $req = new CheckToken($this->user);
      $this->success = $req->execute(array("token" => $token));
      $this->lastError = $req->getLastError();

      if (!$this->success) {
        return false;
      }

      $result = $req->getResult();
      if (strcasecmp($result["token"]["type"], "invite") !== 0) {
        return $this->createError("Invalid token type");
      } else if($result["user"]["confirmed"]) {
        return $this->createError("Your email address is already confirmed.");
      } else if (!$this->checkPasswordRequirements($password, $confirmPassword)) {
        return false;
      } else if (!$this->updateUser($result["user"]["uid"], $password)) {
        return false;
      } else {

        // Invalidate token
        $this->user->getSQL()
          ->update("UserToken")
          ->set("used", true)
          ->where(new Compare("token", $token))
          ->execute();

        return true;
      }
    }
  }

  class ConfirmEmail extends UserAPI {

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'token' => new StringType('token', 36)
      ));
      $this->csrfTokenRequired = false;
    }

    private function updateUser($uid): bool {
      $sql = $this->user->getSQL();
      $res = $sql->update("User")
        ->set("confirmed", true)
        ->where(new Compare("uid", $uid))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    public function _execute(): bool {

      if ($this->user->isLoggedIn()) {
        return $this->createError("You are already logged in.");
      }

      $token = $this->getParam("token");
      $req = new CheckToken($this->user);
      $this->success = $req->execute(array("token" => $token));
      $this->lastError = $req->getLastError();

      if ($this->success) {
        $result = $req->getResult();
        if (strcasecmp($result["token"]["type"], "email_confirm") !== 0) {
          return $this->createError("Invalid token type");
        } else if($result["user"]["confirmed"]) {
          return $this->createError("Your email address is already confirmed.");
        } else if (!$this->updateUser($result["user"]["uid"])) {
          return false;
        } else {
          $this->invalidateToken($token);
          return true;
        }
      }

      return $this->success;
    }
  }

  class Login extends UserAPI {

    private int $startedAt;

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'username' => new StringType('username'),
        'password' => new StringType('password'),
        'stayLoggedIn' => new Parameter('stayLoggedIn', Parameter::TYPE_BOOLEAN, true, false)
      ));
      $this->forbidMethod("GET");
    }

    private function wrongCredentials(): bool {
      $runtime = microtime(true) - $this->startedAt;
      $sleepTime = round(3e6 - $runtime);
      if ($sleepTime > 0) usleep($sleepTime);
      return $this->createError(L('Wrong username or password'));
    }

    public function _execute(): bool {

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
      $res = $sql->select("User.uid", "User.password", "User.confirmed",
          "User.2fa_id", "2FA.type as 2fa_type", "2FA.confirmed as 2fa_confirmed", "2FA.data as 2fa_data")
        ->from("User")
        ->where(new Compare("User.name", $username), new Compare("User.email", $username))
        ->leftJoin("2FA", "2FA.uid", "User.2fa_id")
        ->limit(1)
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if (!is_array($res) || count($res) === 0) {
          return $this->wrongCredentials();
        } else {
          $row = $res[0];
          $uid = $row['uid'];
          $confirmed = $sql->parseBool($row["confirmed"]);
          $token = $row["2fa_id"] ? TwoFactorToken::newInstance($row["2fa_type"], $row["2fa_data"], $row["2fa_id"], $sql->parseBool($row["2fa_confirmed"])) : null;
          if (password_verify($password, $row['password'])) {
            if (!$confirmed) {
              $this->result["emailConfirmed"] = false;
              return $this->createError("Your email address has not been confirmed yet.");
            } else if (!($this->success = $this->user->createSession($uid, $stayLoggedIn))) {
              return $this->createError("Error creating Session: " . $sql->getLastError());
            } else {
              $this->result["loggedIn"] = true;
              $this->result["logoutIn"] = $this->user->getSession()->getExpiresSeconds();
              $this->result["csrf_token"] = $this->user->getSession()->getCsrfToken();
              if ($token && $token->isConfirmed()) {
                $this->result["2fa"] = ["type" => $token->getType()];
                if ($token instanceof KeyBasedTwoFactorToken) {
                  $challenge = base64_encode(generateRandomString(32, "raw"));
                  $this->result["2fa"]["challenge"] = $challenge;
                  $_SESSION["challenge"] = $challenge;
                }
              }
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
      $this->loginRequired = false;
      $this->apiKeyAllowed = false;
      $this->forbidMethod("GET");
    }

    public function _execute(): bool {

      if (!$this->user->isLoggedIn()) {
        return $this->createError("You are not logged in.");
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
      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {

      if ($this->user->isLoggedIn()) {
        return $this->createError(L('You are already logged in'));
      }

      $registrationAllowed = $this->user->getConfiguration()->getSettings()->isRegistrationAllowed();
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
      $password = $this->getParam("password");
      $confirmPassword = $this->getParam("confirmPassword");
      if (!$this->checkUserExists($username, $email)) {
        return false;
      }

      if(!$this->checkRequirements($username, $password, $confirmPassword)) {
        return false;
      }

      $fullName = substr($email, 0, strrpos($email, "@"));
      $fullName = implode(" ", array_map(function ($part) {
        return ucfirst(strtolower($part));
        }, explode(".", $fullName))
      );

      $this->userId = $this->insertUser($username, $email, $password, false, $fullName);
      if (!$this->success) {
        return false;
      }

      $validHours = 48;
      $this->token = generateRandomString(36);
      if ($this->insertToken($this->userId, $this->token, "email_confirm", $validHours)) {

        $settings = $this->user->getConfiguration()->getSettings();
        $baseUrl = $settings->getBaseUrl();
        $siteName = $settings->getSiteName();
        $req = new Render($this->user);
        $this->success = $req->execute([
          "file" => "mail/confirm_email.twig",
          "parameters" => [
            "link" => "$baseUrl/confirmEmail?token=$this->token",
            "site_name" => $siteName,
            "base_url" => $baseUrl,
            "username" => $username,
            "valid_time" => $this->formatDuration($validHours, "hour")
          ]
        ]);
        $this->lastError = $req->getLastError();

        if ($this->success) {
          $messageBody = $req->getResult()["html"];
          $request = new \Api\Mail\Send($this->user);
          $this->success = $request->execute(array(
            "to" => $email,
            "subject" => "[$siteName] E-Mail Confirmation",
            "body" => $messageBody,
            "async" => true,
          ));
          $this->lastError = $request->getLastError();
        }
      }

      if (!$this->success) {
        $this->logger->error("Could not deliver email to=$email type=register reason=" . $this->lastError);
        $this->lastError = "Your account was registered but the confirmation email could not be sent. " .
          "Please contact the server administration. This issue has been automatically logged. Reason: " . $this->lastError;
      }

      $this->logger->info("Registered new user with uid=" . $this->userId);
      return $this->success;
    }
  }

  class CheckToken extends UserAPI {

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'token' => new StringType('token', 36),
      ));
    }

    private function checkToken($token) {
      $sql = $this->user->getSQL();
      $res = $sql->select("UserToken.token_type", "User.uid", "User.name", "User.email", "User.confirmed")
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

    public function _execute(): bool {

      $token = $this->getParam('token');
      $tokenEntry = $this->checkToken($token);

      if ($this->success) {
        if (!empty($tokenEntry)) {
          $this->result["token"] = array(
            "type" => $tokenEntry["token_type"]
          );

          $this->result["user"] = array(
            "name" => $tokenEntry["name"],
            "email" => $tokenEntry["email"],
            "uid" => $tokenEntry["uid"],
            "confirmed" => $tokenEntry["confirmed"]
          );
        } else {
          return $this->createError("This token does not exist or is no longer valid");
        }
      }
      return $this->success;
    }
  }

  class Edit extends UserAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'id' => new Parameter('id', Parameter::TYPE_INT),
        'username' => new StringType('username', 32, true, NULL),
        'fullName' => new StringType('fullName', 64, true, NULL),
        'email' => new Parameter('email', Parameter::TYPE_EMAIL, true, NULL),
        'password' => new StringType('password', -1, true, NULL),
        'groups' => new Parameter('groups', Parameter::TYPE_ARRAY, true, NULL),
        'confirmed' => new Parameter('confirmed', Parameter::TYPE_BOOLEAN, true, NULL)
      ));

      $this->loginRequired = true;
      $this->forbidMethod("GET");
    }

    public function _execute(): bool {

      $id = $this->getParam("id");
      $user = $this->getUser($id);

      if ($this->success) {
        if (empty($user)) {
          return $this->createError("User not found");
        }

        $username = $this->getParam("username");
        $fullName = $this->getParam("fullName");
        $email = $this->getParam("email");
        $password = $this->getParam("password");
        $groups = $this->getParam("groups");
        $confirmed = $this->getParam("confirmed");

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
        $usernameChanged = !is_null($username) && strcasecmp($username, $user[0]["name"]) !== 0;
        $fullNameChanged = !is_null($fullName) && strcasecmp($fullName, $user[0]["fullName"]) !== 0;
        $emailChanged = !is_null($email) && strcasecmp($email, $user[0]["email"]) !== 0;
        if($usernameChanged || $emailChanged) {
          if (!$this->checkUserExists($usernameChanged ? $username : NULL, $emailChanged ? $email : NULL)) {
            return false;
          }
        }

        $sql = $this->user->getSQL();
        $query = $sql->update("User");

        if ($usernameChanged) $query->set("name", $username);
        if ($fullNameChanged) $query->set("fullName", $fullName);
        if ($emailChanged) $query->set("email", $email);
        if (!is_null($password)) $query->set("password", $this->hashPassword($password));

        if (!is_null($confirmed)) {
          if ($id === $this->user->getId() && $confirmed === false) {
            return $this->createError("Cannot make own account unconfirmed.");
          } else {
            $query->set("confirmed", $confirmed);
          }
        }

        if (!empty($query->getValues())) {
          $query->where(new Compare("User.uid", $id));
          $res = $query->execute();
          $this->lastError = $sql->getLastError();
          $this->success = ($res !== FALSE);
        }

        if ($this->success) {

          $deleteQuery = $sql->delete("UserGroup")->where(new Compare("user_id", $id));
          $insertQuery = $sql->insert("UserGroup", array("user_id", "group_id"));

          foreach($groupIds as $groupId) {
            $insertQuery->addRow($id, $groupId);
          }

          $this->success = ($deleteQuery->execute() !== FALSE) && (empty($groupIds) || $insertQuery->execute() !== FALSE);
          $this->lastError = $sql->getLastError();
        }
      }

      return $this->success;
    }
  }

  class Delete extends UserAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'id' => new Parameter('id', Parameter::TYPE_INT)
      ));

      $this->loginRequired = true;
    }

    public function _execute(): bool {

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

  class RequestPasswordReset extends UserAPI {
    public function __construct(User $user, $externalCall = false) {
      $parameters = array(
        'email' => new Parameter('email', Parameter::TYPE_EMAIL),
      );

      $settings = $user->getConfiguration()->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $parameters["captcha"] = new StringType("captcha");
      }

      parent::__construct($user, $externalCall, $parameters);
    }

    public function _execute(): bool {

      if ($this->user->isLoggedIn()) {
        return $this->createError("You already logged in.");
      }

      $settings = $this->user->getConfiguration()->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $captcha = $this->getParam("captcha");
        $req = new VerifyCaptcha($this->user);
        if (!$req->execute(array("captcha" => $captcha, "action" => "resetPassword"))) {
          return $this->createError($req->getLastError());
        }
      }

      $email = $this->getParam("email");
      $user = $this->findUser($email);
      if ($this->success === false) {
        return false;
      }

      if ($user !== null) {
        $validHours = 1;
        $token = generateRandomString(36);
        if (!$this->insertToken($user["uid"], $token, "password_reset", $validHours)) {
          return false;
        }

        $baseUrl = $settings->getBaseUrl();
        $siteName = $settings->getSiteName();

        $req = new Render($this->user);
        $this->success = $req->execute([
          "file" => "mail/reset_password.twig",
          "parameters" => [
            "link" => "$baseUrl/resetPassword?token=$token",
            "site_name" => $siteName,
            "base_url" => $baseUrl,
            "username" => $user["name"],
            "valid_time" => $this->formatDuration($validHours, "hour")
          ]
        ]);
        $this->lastError = $req->getLastError();

        if ($this->success) {
          $messageBody = $req->getResult()["html"];

          $gpgFingerprint = null;
          if ($user["gpg_id"] && $user["gpg_confirmed"]) {
            $gpgFingerprint = $user["gpg_fingerprint"];
          }

          $request = new \Api\Mail\Send($this->user);
          $this->success = $request->execute(array(
            "to" => $email,
            "subject" => "[$siteName] Password Reset",
            "body" => $messageBody,
            "gpgFingerprint" => $gpgFingerprint
          ));
          $this->lastError = $request->getLastError();
          $this->logger->info("Requested password reset for user uid=" . $user["uid"] . " by ip_address=" . $_SERVER["REMOTE_ADDR"]);
        }
      }

      return $this->success;
    }

    private function findUser($email): ?array {
      $sql = $this->user->getSQL();
      $res = $sql->select("User.uid", "User.name",
          "User.gpg_id", "GpgKey.confirmed as gpg_confirmed", "GpgKey.fingerprint as gpg_fingerprint")
        ->from("User")
        ->leftJoin("GpgKey", "GpgKey.uid", "User.gpg_id")
        ->where(new Compare("User.email", $email))
        ->where(new CondBool("User.confirmed"))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      if ($this->success) {
        if (!empty($res)) {
          return $res[0];
        }
      }

      return null;
    }
  }

  class ResendConfirmEmail extends UserAPI {
    public function __construct(User $user, $externalCall = false) {
      $parameters = array(
        'email' => new Parameter('email', Parameter::TYPE_EMAIL),
      );

      $settings = $user->getConfiguration()->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $parameters["captcha"] = new StringType("captcha");
      }

      parent::__construct($user, $externalCall, $parameters);
    }

    public function _execute(): bool {

      if ($this->user->isLoggedIn()) {
        return $this->createError("You already logged in.");
      }

      $settings = $this->user->getConfiguration()->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $captcha = $this->getParam("captcha");
        $req = new VerifyCaptcha($this->user);
        if (!$req->execute(array("captcha" => $captcha, "action" => "resendConfirmation"))) {
          return $this->createError($req->getLastError());
        }
      }

      $email = $this->getParam("email");
      $sql = $this->user->getSQL();
      $res = $sql->select("User.uid", "User.name", "UserToken.token", "UserToken.token_type", "UserToken.used")
        ->from("User")
        ->leftJoin("UserToken", "User.uid", "UserToken.user_id")
        ->where(new Compare("User.email", $email))
        ->where(new Compare("User.confirmed", false))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return $this->createError($sql->getLastError());
      } else if (!is_array($res) || empty($res)) {
        // user does not exist
        return true;
      }

      $userId = $res[0]["uid"];
      $token = current(
        array_map(function ($row) {
          return $row["token"];
        }, array_filter($res, function ($row) use ($sql) {
          return !$sql->parseBool($row["used"]) && $row["token_type"] === "email_confirm";
        }))
      );

      $validHours = 48;
      if (!$token) {
        // no token generated yet, let's generate one
        $token = generateRandomString(36);
        if (!$this->insertToken($userId, $token, "email_confirm", $validHours)) {
          return false;
        }
      } else {
        $sql->update("UserToken")
          ->set("valid_until", (new DateTime())->modify("+$validHours hour"))
          ->where(new Compare("token", $token))
          ->execute();
      }

      $username = $res[0]["name"];
      $baseUrl = $settings->getBaseUrl();
      $siteName = $settings->getSiteName();

      $req = new Render($this->user);
      $this->success = $req->execute([
        "file" => "mail/confirm_email.twig",
        "parameters" => [
          "link" => "$baseUrl/confirmEmail?token=$token",
          "site_name" => $siteName,
          "base_url" => $baseUrl,
          "username" => $username,
          "valid_time" => $this->formatDuration($validHours, "hour")
        ]
      ]);
      $this->lastError = $req->getLastError();

      if ($this->success) {
        $messageBody = $req->getResult()["html"];
        $request = new \Api\Mail\Send($this->user);
        $this->success = $request->execute(array(
          "to" => $email,
          "subject" => "[$siteName] E-Mail Confirmation",
          "body" => $messageBody
        ));

        $this->lastError = $request->getLastError();
      }

      return $this->success;
    }
  }

  class ResetPassword extends UserAPI {

    public function __construct(User $user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'token' => new StringType('token', 36),
        'password' => new StringType('password'),
        'confirmPassword' => new StringType('confirmPassword'),
      ));

      $this->csrfTokenRequired = false;
    }

    private function updateUser($uid, $password): bool {
      $sql = $this->user->getSQL();
      $res = $sql->update("User")
        ->set("password", $this->hashPassword($password))
        ->where(new Compare("uid", $uid))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    public function _execute(): bool {

      if ($this->user->isLoggedIn()) {
        return $this->createError("You are already logged in.");
      }

      $token = $this->getParam("token");
      $password = $this->getParam("password");
      $confirmPassword = $this->getParam("confirmPassword");

      $req = new CheckToken($this->user);
      $this->success = $req->execute(array("token" => $token));
      $this->lastError = $req->getLastError();
      if (!$this->success) {
        return false;
      }

      $result = $req->getResult();
      if (strcasecmp($result["token"]["type"], "password_reset") !== 0) {
        return $this->createError("Invalid token type");
      } else if (!$this->checkPasswordRequirements($password, $confirmPassword)) {
        return false;
      } else if (!$this->updateUser($result["user"]["uid"], $password)) {
        return false;
      } else {
        $this->logger->info("Issued password reset for user uid=" . $result["user"]["uid"]);
        $this->invalidateToken($token);
        return true;
      }
    }
  }

  class UpdateProfile extends UserAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'username' => new StringType('username', 32, true, NULL),
        'fullName' => new StringType('fullName', 64, true, NULL),
        'password' => new StringType('password', -1, true, NULL),
        'confirmPassword' => new StringType('confirmPassword', -1, true, NULL),
        'oldPassword' => new StringType('oldPassword', -1, true, NULL),
      ));
      $this->loginRequired = true;
      $this->csrfTokenRequired = true;
      $this->forbidMethod("GET");
    }

    public function _execute(): bool {

      $newUsername = $this->getParam("username");
      $oldPassword = $this->getParam("oldPassword");
      $newPassword = $this->getParam("password");
      $newPasswordConfirm = $this->getParam("confirmPassword");
      $newFullName = $this->getParam("fullName");

      if ($newUsername === null && $newPassword === null && $newPasswordConfirm === null && $newFullName === null) {
        return $this->createError("You must either provide an updated username, fullName or password");
      }

      $sql = $this->user->getSQL();
      $query = $sql->update("User")->where(new Compare("uid", $this->user->getId()));
      if ($newUsername !== null) {
        if (!$this->checkUsernameRequirements($newUsername) || !$this->checkUserExists($newUsername)) {
          return false;
        } else {
          $query->set("name", $newUsername);
        }
      }

      if ($newFullName !== null) {
        $query->set("fullName", $newFullName);
      }

      if ($newPassword !== null || $newPasswordConfirm !== null) {
        if (!$this->checkPasswordRequirements($newPassword, $newPasswordConfirm)) {
          return false;
        } else {
          $res = $sql->select("password")
            ->from("User")
            ->where(new Compare("uid", $this->user->getId()))
            ->execute();

          $this->success = ($res !== false);
          $this->lastError = $sql->getLastError();
          if (!$this->success) {
            return false;
          }

          if (!password_verify($oldPassword, $res[0]["password"])) {
            return $this->createError("Wrong password");
          }

          $query->set("password", $this->hashPassword($newPassword));
        }
      }

      $this->success = $query->execute();
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }

  class ImportGPG extends UserAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "pubkey" => new StringType("pubkey")
      ));
      $this->loginRequired = true;
      $this->forbidMethod("GET");
    }

    private function testKey(string $keyString) {
      $res = GpgKey::getKeyInfo($keyString);
      if (!$res["success"]) {
        return $this->createError($res["error"]);
      }

      $keyData = $res["data"];
      $keyType = $keyData["type"];
      $expires = $keyData["expires"];

      if ($keyType === "sec#") {
        return self::createError("ATTENTION! It seems like you've imported a PGP PRIVATE KEY instead of a public key. 
            It is recommended to immediately revoke your private key and create a new key pair.");
      } else if ($keyType !== "pub") {
        return self::createError("Unknown key type: $keyType");
      } else if (isInPast($expires)) {
        return self::createError("It seems like the gpg key is already expired.");
      } else {
        return $keyData;
      }
    }

    public function _execute(): bool {

      $gpgKey = $this->user->getGPG();
      if ($gpgKey) {
        return $this->createError("You already added a GPG key to your account.");
      }

      // fix key first, enforce a newline after
      $keyString = $this->getParam("pubkey");
      $keyString = preg_replace("/(-{2,})\n([^\n])/", "$1\n\n$2", $keyString);
      $keyData = $this->testKey($keyString);
      if ($keyData === false) {
        return false;
      }

      $res = GpgKey::importKey($keyString);
      if (!$res["success"]) {
        return $this->createError($res["error"]);
      }

      $sql = $this->user->getSQL();
      $res = $sql->insert("GpgKey", ["fingerprint", "algorithm", "expires"])
        ->addRow($keyData["fingerprint"], $keyData["algorithm"], $keyData["expires"])
        ->returning("uid")
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      }

      $gpgKeyId = $sql->getLastInsertId();
      $res = $sql->update("User")
        ->set("gpg_id", $gpgKeyId)
        ->where(new Compare("uid", $this->user->getId()))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      }

      $token = generateRandomString(36);
      $res = $sql->insert("UserToken", ["user_id", "token", "token_type", "valid_until"])
        ->addRow($this->user->getId(), $token, "gpg_confirm", (new DateTime())->modify("+1 hour"))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      }

      $name = htmlspecialchars($this->user->getFullName());
      if (!$name) {
        $name = htmlspecialchars($this->user->getUsername());
      }

      $settings = $this->user->getConfiguration()->getSettings();
      $baseUrl = htmlspecialchars($settings->getBaseUrl());
      $token = htmlspecialchars(urlencode($token));
      $url = "$baseUrl/settings?confirmGPG&token=$token";
      $mailBody = "Hello $name,<br><br>" .
        "you imported a GPG public key for end-to-end encrypted mail communication. " .
        "To confirm the key and verify, you own the corresponding private key, please click on the following link. " .
        "The link is active for one hour.<br><br>" .
        "<a href='$url'>$url</a><br>
        Best Regards<br>
        ilum:e Security Lab";

      $sendMail = new \Api\Mail\Send($this->user);
      $this->success = $sendMail->execute(array(
        "to" => $this->user->getEmail(),
        "subject" => "Security Lab - Confirm GPG-Key",
        "body" => $mailBody,
        "gpgFingerprint" => $keyData["fingerprint"]
      ));

      $this->lastError = $sendMail->getLastError();

      if ($this->success) {
        $this->result["gpg"] = array(
          "fingerprint" => $keyData["fingerprint"],
          "confirmed" => false,
          "algorithm" => $keyData["algorithm"],
          "expires" => $keyData["expires"]->getTimestamp()
        );
      }

      return $this->success;
    }
  }

  class RemoveGPG extends UserAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "password" => new StringType("password")
      ));
      $this->loginRequired = true;
      $this->forbidMethod("GET");
    }

    public function _execute(): bool {

      $gpgKey = $this->user->getGPG();
      if (!$gpgKey) {
        return $this->createError("You have not added a GPG public key to your account yet.");
      }

      $sql = $this->user->getSQL();
      $res = $sql->select("password")
        ->from("User")
        ->where(new Compare("User.uid", $this->user->getId()))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success && is_array($res)) {
        $hash = $res[0]["password"];
        $password = $this->getParam("password");
        if (!password_verify($password, $hash)) {
          return $this->createError("Incorrect password.");
        } else {
          $res = $sql->delete("GpgKey")
            ->where(new Compare("uid",
              $sql->select("User.gpg_id")
                  ->from("User")
                  ->where(new Compare("User.uid", $this->user->getId()))
            ))->execute();
          $this->success = ($res !== false);
          $this->lastError = $sql->getLastError();
        }
      }

      return $this->success;
    }
  }

  class ConfirmGPG extends UserAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, [
        "token" => new StringType("token", 36)
      ]);
      $this->loginRequired = true;
    }

    public function _execute(): bool {

      $gpgKey = $this->user->getGPG();
      if (!$gpgKey) {
        return $this->createError("You have not added a GPG key yet.");
      } else if ($gpgKey->isConfirmed()) {
        return $this->createError("Your GPG key is already confirmed");
      }

      $token = $this->getParam("token");
      $sql = $this->user->getSQL();
      $res = $sql->select($sql->count())
        ->from("UserToken")
        ->where(new Compare("token", $token))
        ->where(new Compare("valid_until", $sql->now(), ">="))
        ->where(new Compare("user_id", $this->user->getId()))
        ->where(new Compare("token_type", "gpg_confirm"))
        ->where(new CondNot(new CondBool("used")))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();

      if ($this->success && is_array($res)) {
        if ($res[0]["count"] === 0) {
          return $this->createError("Invalid token");
        } else {
          $res = $sql->update("GpgKey")
            ->set("confirmed", 1)
            ->where(new Compare("uid", $gpgKey->getId()))
            ->execute();

          $this->success = ($res !== false);
          $this->lastError = $sql->getLastError();
          if (!$this->success) {
            return false;
          }

          $res = $sql->update("UserToken")
            ->set("used", 1)
            ->where(new Compare("token", $token))
            ->execute();

          $this->success = ($res !== false);
          $this->lastError = $sql->getLastError();
        }
      }

      return $this->success;
    }
  }

  class DownloadGPG extends UserAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "id" => new Parameter("id", Parameter::TYPE_INT, true, null),
        "format" => new StringType("format", 16, true, "ascii")
      ));
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {

      $allowedFormats = ["json", "ascii", "gpg"];
      $format = $this->getParam("format");
      if (!in_array($format, $allowedFormats)) {
        return $this->getParam("Invalid requested format. Allowed formats: " . implode(",", $allowedFormats));
      }

      $userId = $this->getParam("id");
      if ($userId === null || $userId == $this->user->getId()) {
        $gpgKey = $this->user->getGPG();
        if (!$gpgKey) {
          return $this->createError("You did not add a gpg key yet.");
        }

        $email = $this->user->getEmail();
        $gpgFingerprint = $gpgKey->getFingerprint();
      } else {
        $req = new Get($this->user);
        $this->success = $req->execute(["id" => $userId]);
        $this->lastError = $req->getLastError();
        if (!$this->success) {
          return false;
        }

        $res = $req->getResult()["user"];
        $email = $res["email"];
        $gpgFingerprint = $res["gpgFingerprint"];
        if (!$gpgFingerprint) {
          return $this->createError("This user has not added a gpg key yet");
        }
      }

      $res = GpgKey::export($gpgFingerprint, $format !== "gpg");
      if (!$res["success"]) {
        return $this->createError($res["error"]);
      }

      $key = $res["data"];
      if ($format === "json") {
        $this->result["key"] = $key;
        return true;
      } else if ($format === "ascii") {
        $contentType = "application/pgp-keys";
        $ext = "asc";
      } else if ($format === "gpg") {
        $contentType = "application/octet-stream";
        $ext = "gpg";
      } else {
        die("Invalid format");
      }

      $fileName = "$email.$ext";
      header("Content-Type: $contentType");
      header("Content-Length: " . strlen($key));
      header("Content-Disposition: attachment; filename=\"$fileName\"");
      die($key);
    }
  }

  class UploadPicture extends UserAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, [
        "scale" => new Parameter("scale", Parameter::TYPE_FLOAT, true, NULL),
      ]);
      $this->loginRequired = true;
      $this->forbidMethod("GET");
    }

    /**
     * @throws ImagickException
     */
    protected function onTransform(\Imagick $im, $uploadDir) {

      $minSize = 75;
      $maxSize = 500;

      $width = $im->getImageWidth();
      $height = $im->getImageHeight();
      $doResize = false;

      if ($width < $minSize || $height < $minSize) {
        if ($width < $height) {
          $newWidth = $minSize;
          $newHeight = intval(($minSize / $width) * $height);
        } else {
          $newHeight = $minSize;
          $newWidth = intval(($minSize / $height) * $width);
        }

        $doResize = true;
      } else if ($width > $maxSize || $height > $maxSize) {
        if ($width > $height) {
          $newWidth = $maxSize;
          $newHeight = intval($height * ($maxSize / $width));
        } else {
          $newHeight = $maxSize;
          $newWidth = intval($width * ($maxSize / $height));
        }

        $doResize = true;
      } else {
        $newWidth = $width;
        $newHeight = $height;
      }

      if ($width < $minSize || $height < $minSize) {
        return $this->createError("Error processing image. Bad dimensions.");
      }

      if ($doResize) {
        $width = $newWidth;
        $height = $newHeight;
        $im->resizeImage($width, $height, \Imagick::FILTER_SINC, 1);
      }

      $size = $this->getParam("size");
      if (is_null($size)) {
        $size = min($width, $height);
      }

      $offset = [$this->getParam("offsetX"), $this->getParam("offsetY")];
      if ($size < $minSize or $size > $maxSize) {
        return $this->createError("Invalid size. Must be in range of $minSize-$maxSize.");
      }/* else if ($offset[0] < 0 || $offset[1] < 0 || $offset[0]+$size > $width ||  $offset[1]+$size > $height) {
        return $this->createError("Offsets out of bounds.");
      }*/

      if ($offset[0] !== 0 || $offset[1] !== 0 || $size !== $width || $size !== $height) {
        $im->cropImage($size, $size, $offset[0], $offset[1]);
      }

      $fileName = uuidv4() . ".jpg";
      $im->writeImage("$uploadDir/$fileName");
      $im->destroy();
      return $fileName;
    }

    public function _execute(): bool {

      $userId = $this->user->getId();
      $uploadDir = WEBROOT . "/img/uploads/user/$userId";
      list ($fileName, $imageName) = $this->processImageUpload($uploadDir, ["png","jpg","jpeg"], "onTransform");
      if (!$this->success) {
        return false;
      }

      $oldPfp = $this->user->getProfilePicture();
      if ($oldPfp) {
        $path = "$uploadDir/$oldPfp";
        if (is_file($path)) {
          @unlink($path);
        }
      }

      $sql = $this->user->getSQL();
      $this->success = $sql->update("User")
        ->set("profilePicture", $fileName)
        ->where(new Compare("uid", $userId))
        ->execute();

      $this->lastError = $sql->getLastError();
      if ($this->success) {
        $this->result["profilePicture"] = $fileName;
      }

      return $this->success;
    }
  }

  class RemovePicture extends UserAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, []);
      $this->loginRequired = true;
    }

    public function _execute(): bool {

      $pfp = $this->user->getProfilePicture();
      if (!$pfp) {
        return $this->createError("You did not upload a profile picture yet");
      }

      $userId = $this->user->getId();
      $sql = $this->user->getSQL();
      $this->success = $sql->update("User")
        ->set("profilePicture", NULL)
        ->where(new Compare("uid", $userId))
        ->execute();
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $path = WEBROOT . "/img/uploads/user/$userId/$pfp";
        if (is_file($path)) {
          @unlink($path);
        }
      }

      return $this->success;
    }
  }
}