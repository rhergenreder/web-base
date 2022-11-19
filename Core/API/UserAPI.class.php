<?php

namespace Core\API {

  use Core\Driver\SQL\Condition\Compare;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Language;
  use Core\Objects\DatabaseEntity\User;
  use Core\Objects\DatabaseEntity\UserToken;

  abstract class UserAPI extends Request {

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }

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

      $sql = $this->context->getSQL();
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
      } else if (strlen($password) < 6) {
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

    protected function insertUser($username, $email, $password, $confirmed, $fullName = ""): bool|User {
      $sql = $this->context->getSQL();

      $user = new User();
      $user->language = Language::DEFAULT_LANGUAGE(false);
      $user->registeredAt = new \DateTime();
      $user->password = $this->hashPassword($password);
      $user->name = $username;
      $user->email = $email;
      $user->confirmed = $confirmed;
      $user->fullName = $fullName ?? "";

      $this->success = ($user->save($sql) !== FALSE);
      $this->lastError = $sql->getLastError();

      return $this->success ? $user : false;
    }

    protected function hashPassword($password): string {
      return password_hash($password, PASSWORD_BCRYPT);
    }

    protected function formatDuration(int $count, string $string): string {
      if ($count === 1) {
        return $string;
      } else {
        return "the next $count ${string}s";
      }
    }

    protected function checkToken(string $token) : UserToken|bool {
      $sql = $this->context->getSQL();
      $userToken = UserToken::findBuilder($sql)
        ->where(new Compare("UserToken.token", $token))
        ->where(new Compare("UserToken.valid_until", $sql->now(), ">"))
        ->where(new Compare("UserToken.used", 0))
        ->fetchEntities()
        ->execute();

      if ($userToken === false) {
        return $this->createError("Error verifying token: " . $sql->getLastError());
      } else if ($userToken === null) {
        return $this->createError("This token does not exist or is no longer valid");
      } else {
        return $userToken;
      }
    }
  }

}

namespace Core\API\User {

  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\API\Template\Render;
  use Core\API\UserAPI;
  use Core\API\VerifyCaptcha;
  use Core\Objects\DatabaseEntity\UserToken;
  use Core\Driver\SQL\Column\Column;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Condition\CondIn;
  use Core\Driver\SQL\Expression\JsonArrayAgg;
  use ImagickException;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\GpgKey;
  use Core\Objects\TwoFactor\KeyBasedTwoFactorToken;
  use Core\Objects\DatabaseEntity\User;

  class Create extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
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
      $user = $this->insertUser($username, $email, $password, true);
      if ($user !== false) {
        $this->result["userId"] = $user->getId();
      }

      return $this->success;
    }
  }

  class Fetch extends UserAPI {

    private int $userCount;

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'page' => new Parameter('page', Parameter::TYPE_INT, true, 1),
        'count' => new Parameter('count', Parameter::TYPE_INT, true, 20)
      ));
    }

    private function getUserCount(): bool {

      $sql = $this->context->getSQL();
      $res = $sql->select($sql->count())->from("User")->execute();
      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->userCount = $res[0]["count"];
      }

      return $this->success;
    }

    private function selectIds($page, $count) {
      $sql = $this->context->getSQL();
      $res = $sql->select("User.id")
        ->from("User")
        ->limit($count)
        ->offset(($page - 1) * $count)
        ->orderBy("User.id")
        ->ascending()
        ->execute();

      $this->success = ($res !== NULL);
      $this->lastError = $sql->getLastError();

      if ($this->success && is_array($res)) {
        return array_map(function ($row) {
          return intval($row["id"]);
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

      $sql = $this->context->getSQL();
      $res = $sql->select("User.id as userId", "User.name", "User.email", "User.registered_at", "User.confirmed",
        "User.profile_picture", "User.full_name", "Group.id as groupId", "User.last_online",
        "Group.name as groupName", "Group.color as groupColor")
        ->from("User")
        ->leftJoin("UserGroup", "User.id", "UserGroup.user_id")
        ->leftJoin("Group", "Group.id", "UserGroup.group_id")
        ->where(new CondIn(new Column("User.id"), $userIds))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      $currentUser = $this->context->getUser();

      if ($this->success) {
        $this->result["users"] = array();
        foreach ($res as $row) {
          $userId = intval($row["userId"]);
          $groupId = $row["groupId"];
          $groupName = $row["groupName"];
          $groupColor = $row["groupColor"];

          $fullInfo = ($userId === $currentUser->getId() ||
            $currentUser->hasGroup(USER_GROUP_ADMIN) ||
            $currentUser->hasGroup(USER_GROUP_SUPPORT));

          if (!isset($this->result["users"][$userId])) {
            $user = array(
              "id" => $userId,
              "name" => $row["name"],
              "fullName" => $row["full_name"],
              "profilePicture" => $row["profile_picture"],
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
            $this->result["users"][$userId]["groups"][intval($groupId)] = array(
              "id" => intval($groupId),
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

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'id' => new Parameter('id', Parameter::TYPE_INT)
      ));
      $this->loginRequired = true;
    }

    public function _execute(): bool {

      $sql = $this->context->getSQL();
      $userId = $this->getParam("id");
      $user = User::find($sql, $userId, true);
      if ($user === false) {
        return $this->createError("Error querying user: " . $sql->getLastError());
      } else if ($user === null) {
        return $this->createError("User not found");
      } else {

        $queriedUser = $user->jsonSerialize();

        // either we are querying own info or we are support / admin
        $currentUser = $this->context->getUser();
        $canView = ($userId === $currentUser->getId() ||
          $currentUser->hasGroup(USER_GROUP_ADMIN) ||
          $currentUser->hasGroup(USER_GROUP_SUPPORT));

        // full info only when we have administrative privileges, or we are querying ourselves
        $fullInfo = ($userId === $currentUser->getId() ||
          $currentUser->hasGroup(USER_GROUP_ADMIN) ||
          $currentUser->hasGroup(USER_GROUP_SUPPORT));

        if (!$canView) {

          // check if user posted something publicly
          $res = $sql->select(new JsonArrayAgg(new Column("publishedBy"), "publisherIds"))
            ->from("News")
            ->execute();
          $this->success = ($res !== false);
          $this->lastError = $sql->getLastError();
          if (!$this->success) {
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

          $publicAttributes = ["id", "name", "fullName", "profilePicture", "email", "groups"];
          foreach (array_keys($queriedUser) as $attr) {
            if (!in_array($attr, $publicAttributes)) {
              unset($queriedUser[$attr]);
            }
          }
        }

        unset($queriedUser["session"]); // strip session information
        $this->result["user"] = $queriedUser;
      }

      return $this->success;
    }
  }

  class Info extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array());
      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      if (!$currentUser) {
        $this->result["loggedIn"] = false;
      } else {
        $this->result["loggedIn"] = true;
        $userGroups = array_keys($currentUser->getGroups());
        $sql = $this->context->getSQL();
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
        $this->result["user"] = $currentUser->jsonSerialize();
      }

      return $this->success;
    }
  }

  class Invite extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
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
      $user = $this->insertUser($username, $email, "", false);
      if ($user === false) {
        return false;
      }

      // Create Token
      $token = generateRandomString(36);
      $validDays = 7;
      $sql = $this->context->getSQL();
      $userToken = new UserToken($user, $token, UserToken::TYPE_INVITE, $validDays * 24);

      if ($userToken->save($sql)) {
        //send validation mail
        $settings = $this->context->getSettings();
        $baseUrl = $settings->getBaseUrl();
        $siteName = $settings->getSiteName();

        $req = new Render($this->context);
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
          $request = new \Core\API\Mail\Send($this->context);
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

      $this->logger->info("Created new user with id=" . $user->getId());
      return $this->success;
    }
  }

  class AcceptInvite extends UserAPI {
    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'token' => new StringType('token', 36),
        'password' => new StringType('password'),
        'confirmPassword' => new StringType('confirmPassword'),
      ));
      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {

      if ($this->context->getUser()) {
        return $this->createError("You are already logged in.");
      }

      $sql = $this->context->getSQL();
      $token = $this->getParam("token");
      $password = $this->getParam("password");
      $confirmPassword = $this->getParam("confirmPassword");
      $userToken = $this->checkToken($token);
      if ($userToken === false) {
        return false;
      } else if ($userToken->getType() !== UserToken::TYPE_INVITE) {
        return $this->createError("Invalid token type");
      }

      $user = $userToken->getUser();
      if ($user->confirmed) {
        return $this->createError("Your email address is already confirmed.");
      } else if (!$this->checkPasswordRequirements($password, $confirmPassword)) {
        return false;
      } else {
        $user->password = $this->hashPassword($password);
        $user->confirmed = true;
        if ($user->save($sql)) {
          $userToken->invalidate($sql);
          return true;
        } else {
          return $this->createError("Unable to update user details: " . $sql->getLastError());
        }
      }
    }
  }

  class ConfirmEmail extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'token' => new StringType('token', 36)
      ));
      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {

      if ($this->context->getUser()) {
        return $this->createError("You are already logged in.");
      }

      $sql = $this->context->getSQL();
      $token = $this->getParam("token");
      $userToken = $this->checkToken($token);
      if ($userToken === false) {
        return false;
      } else if ($userToken->getType() !== UserToken::TYPE_EMAIL_CONFIRM) {
        return $this->createError("Invalid token type");
      }

      $user = $userToken->getUser();
      if ($user->confirmed) {
        return $this->createError("Your email address is already confirmed.");
      } else {
        $user->confirmed = true;
        if ($user->save($sql)) {
          $userToken->invalidate($sql);
          return true;
        } else {
          return $this->createError("Unable to update user details: " . $sql->getLastError());
        }
      }
    }
  }

  class Login extends UserAPI {

    private int $startedAt;

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
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

      if ($this->context->getUser()) {
        $this->lastError = L('You are already logged in');
        $this->success = true;
        return true;
      }

      $this->startedAt = microtime(true);
      $this->success = false;
      $username = $this->getParam('username');
      $password = $this->getParam('password');
      $stayLoggedIn = $this->getParam('stayLoggedIn');

      $sql = $this->context->getSQL();
      $user = User::findBuilder($sql)
        ->where(new Compare("User.name", $username), new Compare("User.email", $username))
        ->fetchEntities()
        ->execute();

      if ($user !== false) {
        if ($user === null) {
          return $this->wrongCredentials();
        } else {
          if (password_verify($password, $user->password)) {
            if (!$user->confirmed) {
              $this->result["emailConfirmed"] = false;
              return $this->createError("Your email address has not been confirmed yet.");
            } else if (!($session = $this->context->createSession($user, $stayLoggedIn))) {
              return $this->createError("Error creating Session: " . $sql->getLastError());
            } else {
              $tfaToken = $user->getTwoFactorToken();
              $this->result["loggedIn"] = true;
              $this->result["logoutIn"] = $session->getExpiresSeconds();
              $this->result["csrf_token"] = $session->getCsrfToken();
              if ($tfaToken && $tfaToken->isConfirmed()) {
                $this->result["2fa"] = ["type" => $tfaToken->getType()];
                if ($tfaToken instanceof KeyBasedTwoFactorToken) {
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
      } else {
        return $this->createError("Error fetching user details: " . $sql->getLastError());
      }

      return $this->success;
    }
  }

  class Logout extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall);
      $this->loginRequired = false;
      $this->apiKeyAllowed = false;
      $this->forbidMethod("GET");
    }

    public function _execute(): bool {

      $session = $this->context->getSession();
      if (!$session) {
        return $this->createError("You are not logged in.");
      }

      $this->success = $session->destroy();
      $this->lastError = $this->context->getSQL()->getLastError();
      return $this->success;
    }
  }

  class Register extends UserAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      $parameters = array(
        "username" => new StringType("username", 32),
        'email' => new Parameter('email', Parameter::TYPE_EMAIL),
        "password" => new StringType("password"),
        "confirmPassword" => new StringType("confirmPassword"),
      );

      $settings = $context->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $parameters["captcha"] = new StringType("captcha");
      }

      parent::__construct($context, $externalCall, $parameters);
      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {

      if ($this->context->getUser()) {
        return $this->createError(L('You are already logged in'));
      }

      $settings = $this->context->getSettings();
      $registrationAllowed = $settings->isRegistrationAllowed();
      if (!$registrationAllowed) {
        return $this->createError("User Registration is not enabled.");
      }

      if ($settings->isRecaptchaEnabled()) {
        $captcha = $this->getParam("captcha");
        $req = new VerifyCaptcha($this->context);
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

      if (!$this->checkRequirements($username, $password, $confirmPassword)) {
        return false;
      }

      $fullName = substr($email, 0, strrpos($email, "@"));
      $fullName = implode(" ", array_map(function ($part) {
          return ucfirst(strtolower($part));
        }, explode(".", $fullName))
      );

      $sql = $this->context->getSQL();
      $user = $this->insertUser($username, $email, $password, false, $fullName);
      if ($user === false) {
        return false;
      }

      $validHours = 48;
      $token = generateRandomString(36);
      $userToken = new UserToken($user, $token, UserToken::TYPE_EMAIL_CONFIRM, $validHours);

      if ($userToken->save($sql)) {

        $baseUrl = $settings->getBaseUrl();
        $siteName = $settings->getSiteName();
        $req = new Render($this->context);
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
          $request = new \Core\API\Mail\Send($this->context);
          $this->success = $request->execute(array(
            "to" => $email,
            "subject" => "[$siteName] E-Mail Confirmation",
            "body" => $messageBody,
          ));
          $this->lastError = $request->getLastError();
        }
      } else {
        $this->lastError = "Could create user token: " . $sql->getLastError();
        $this->success = false;
      }

      if (!$this->success) {
        $this->logger->error("Could not deliver email to=$email type=register reason=" . $this->lastError);
        $this->lastError = "Your account was registered but the confirmation email could not be sent. " .
          "Please contact the server administration. This issue has been automatically logged. Reason: " . $this->lastError;
      }

      $this->logger->info("Registered new user with id=" . $user->getId());
      return $this->success;
    }
  }

  class Edit extends UserAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
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

      $sql = $this->context->getSQL();
      $currentUser = $this->context->getUser();
      $id = $this->getParam("id");
      $user = User::find($sql, $id, true);

      if ($user !== false) {
        if ($user === null) {
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

          foreach ($groups as $groupId) {
            if (!$param->parseParam($groupId)) {
              $value = print_r($groupId, true);
              return $this->createError("Invalid Type for groupId in parameter groups: '$value' (Required: " . $param->getTypeName() . ")");
            }

            $groupIds[] = $param->value;
          }

          if ($id === $currentUser->getId() && !in_array(USER_GROUP_ADMIN, $groupIds)) {
            return $this->createError("Cannot remove Administrator group from own user.");
          }
        }

        // Check for duplicate username, email
        $usernameChanged = !is_null($username) && strcasecmp($username, $user->name) !== 0;
        $fullNameChanged = !is_null($fullName) && strcasecmp($fullName, $user->fullName) !== 0;
        $emailChanged = !is_null($email) && strcasecmp($email, $user->email) !== 0;
        if ($usernameChanged || $emailChanged) {
          if (!$this->checkUserExists($usernameChanged ? $username : NULL, $emailChanged ? $email : NULL)) {
            return false;
          }
        }

        if ($usernameChanged) $user->name = $username;
        if ($fullNameChanged) $user->fullName = $fullName;
        if ($emailChanged) $user->email = $email;
        if (!is_null($password)) $user->password = $this->hashPassword($password);

        if (!is_null($confirmed)) {
          if ($id === $currentUser->getId() && $confirmed === false) {
            return $this->createError("Cannot make own account unconfirmed.");
          } else {
            $user->confirmed = $confirmed;
          }
        }

        if ($user->save($sql)) {

          $deleteQuery = $sql->delete("UserGroup")->where(new Compare("user_id", $id));
          $insertQuery = $sql->insert("UserGroup", array("user_id", "group_id"));

          foreach ($groupIds as $groupId) {
            $insertQuery->addRow($id, $groupId);
          }

          $this->success = ($deleteQuery->execute() !== FALSE) && (empty($groupIds) || $insertQuery->execute() !== FALSE);
          $this->lastError = $sql->getLastError();
        }
      } else {
        return $this->createError("Error fetching user details: " . $sql->getLastError());
      }

      return $this->success;
    }
  }

  class Delete extends UserAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'id' => new Parameter('id', Parameter::TYPE_INT)
      ));

      $this->loginRequired = true;
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      $id = $this->getParam("id");
      if ($id === $currentUser->getId()) {
        return $this->createError("You cannot delete your own user.");
      }

      $sql = $this->context->getSQL();
      $user = User::find($sql, $id);
      if ($user !== false) {
        if ($user === null) {
          return $this->createError("User not found");
        } else {
          $this->success = ($user->delete($sql) !== FALSE);
          $this->lastError = $sql->getLastError();
        }
      }

      return $this->success;
    }
  }

  class RequestPasswordReset extends UserAPI {
    public function __construct(Context $context, $externalCall = false) {
      $parameters = array(
        'email' => new Parameter('email', Parameter::TYPE_EMAIL),
      );

      $settings = $context->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $parameters["captcha"] = new StringType("captcha");
      }

      parent::__construct($context, $externalCall, $parameters);
    }

    public function _execute(): bool {

      if ($this->context->getUser()) {
        return $this->createError("You already logged in.");
      }

      $settings = $this->context->getSettings();
      if (!$settings->isMailEnabled()) {
        return $this->createError("The mail service is not enabled, please contact the server administration.");
      }

      if ($settings->isRecaptchaEnabled()) {
        $captcha = $this->getParam("captcha");
        $req = new VerifyCaptcha($this->context);
        if (!$req->execute(array("captcha" => $captcha, "action" => "resetPassword"))) {
          return $this->createError($req->getLastError());
        }
      }

      $sql = $this->context->getSQL();
      $email = $this->getParam("email");
      $user = User::findBuilder($sql)
        ->where(new Compare("email", $email))
        ->fetchEntities()
        ->execute();
      if ($user === false) {
        return $this->createError("Could not fetch user details: " . $sql->getLastError());
      } else if ($user !== null) {
        $validHours = 1;
        $token = generateRandomString(36);
        $userToken = new UserToken($user, $token, UserToken::TYPE_PASSWORD_RESET, $validHours);
        if (!$userToken->save($sql)) {
          return $this->createError("Could not create user token: " . $sql->getLastError());
        }

        $baseUrl = $settings->getBaseUrl();
        $siteName = $settings->getSiteName();

        $req = new Render($this->context);
        $this->success = $req->execute([
          "file" => "mail/reset_password.twig",
          "parameters" => [
            "link" => "$baseUrl/resetPassword?token=$token",
            "site_name" => $siteName,
            "base_url" => $baseUrl,
            "username" => $user->name,
            "valid_time" => $this->formatDuration($validHours, "hour")
          ]
        ]);
        $this->lastError = $req->getLastError();

        if ($this->success) {
          $messageBody = $req->getResult()["html"];

          $gpgKey = $user->getGPG();
          $gpgFingerprint = ($gpgKey && $gpgKey->isConfirmed()) ? $gpgKey->getFingerprint() : null;
          $request = new \Core\API\Mail\Send($this->context);
          $this->success = $request->execute(array(
            "to" => $email,
            "subject" => "[$siteName] Password Reset",
            "body" => $messageBody,
            "gpgFingerprint" => $gpgFingerprint
          ));
          $this->lastError = $request->getLastError();
          $this->logger->info("Requested password reset for user id=" . $user->getId() . " by ip_address=" . $_SERVER["REMOTE_ADDR"]);
        }
      }

      return $this->success;
    }
  }

  class ResendConfirmEmail extends UserAPI {
    public function __construct(Context $context, $externalCall = false) {
      $parameters = array(
        'email' => new Parameter('email', Parameter::TYPE_EMAIL),
      );

      $settings = $context->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $parameters["captcha"] = new StringType("captcha");
      }

      parent::__construct($context, $externalCall, $parameters);
    }

    public function _execute(): bool {

      if ($this->context->getUser()) {
        return $this->createError("You already logged in.");
      }

      $settings = $this->context->getSettings();
      if ($settings->isRecaptchaEnabled()) {
        $captcha = $this->getParam("captcha");
        $req = new VerifyCaptcha($this->context);
        if (!$req->execute(array("captcha" => $captcha, "action" => "resendConfirmation"))) {
          return $this->createError($req->getLastError());
        }
      }

      $email = $this->getParam("email");
      $sql = $this->context->getSQL();
      $user = User::findBuilder($sql)
        ->where(new Compare("User.email", $email))
        ->where(new Compare("User.confirmed", false))
        ->execute();

      if ($user === false) {
        return $this->createError("Error retrieving user details: " . $sql->getLastError());
      } else if ($user === null) {
        // token does not exist: ignore!
        return true;
      }

      $userToken = UserToken::findBuilder($sql)
        ->where(new Compare("used", false))
        ->where(new Compare("tokenType", UserToken::TYPE_EMAIL_CONFIRM))
        ->where(new Compare("user_id", $user->getId()))
        ->execute();

      $validHours = 48;
      if ($userToken === false) {
        return $this->createError("Error retrieving token details: " . $sql->getLastError());
      } else if ($userToken === null) {
        // no token generated yet, let's generate one
        $token = generateRandomString(36);
        $userToken = new UserToken($user, $token, UserToken::TYPE_EMAIL_CONFIRM, $validHours);
        if (!$userToken->save($sql)) {
          return $this->createError("Error generating new token: " . $sql->getLastError());
        }
      } else {
        $userToken->updateDurability($sql, $validHours);
      }

      $username = $user->name;
      $baseUrl = $settings->getBaseUrl();
      $siteName = $settings->getSiteName();

      $req = new Render($this->context);
      $this->success = $req->execute([
        "file" => "mail/confirm_email.twig",
        "parameters" => [
          "link" => "$baseUrl/confirmEmail?token=" . $userToken->getToken(),
          "site_name" => $siteName,
          "base_url" => $baseUrl,
          "username" => $username,
          "valid_time" => $this->formatDuration($validHours, "hour")
        ]
      ]);
      $this->lastError = $req->getLastError();

      if ($this->success) {
        $messageBody = $req->getResult()["html"];
        $request = new \Core\API\Mail\Send($this->context);
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

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'token' => new StringType('token', 36),
        'password' => new StringType('password'),
        'confirmPassword' => new StringType('confirmPassword'),
      ));

      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {

      if ($this->context->getUser()) {
        return $this->createError("You are already logged in.");
      }

      $sql = $this->context->getSQL();
      $token = $this->getParam("token");
      $password = $this->getParam("password");
      $confirmPassword = $this->getParam("confirmPassword");
      $userToken = $this->checkToken($token);
      if ($userToken === false) {
        return false;
      } else if ($userToken->getType() !== UserToken::TYPE_PASSWORD_RESET) {
        return $this->createError("Invalid token type");
      }

      $user = $token->getUser();
      if (!$this->checkPasswordRequirements($password, $confirmPassword)) {
        return false;
      } else {
        $user->password = $this->hashPassword($password);
        if ($user->save($sql)) {
          $this->logger->info("Issued password reset for user id=" . $user->getId());
          $userToken->invalidate($sql);
          return true;
        } else {
          return $this->createError("Error updating user details: " . $sql->getLastError());
        }
      }
    }
  }

  class UpdateProfile extends UserAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
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

      $sql = $this->context->getSQL();

      $currentUser = $this->context->getUser();
      if ($newUsername !== null) {
        if (!$this->checkUsernameRequirements($newUsername) || !$this->checkUserExists($newUsername)) {
          return false;
        } else {
          $currentUser->name = $newUsername;
        }
      }

      if ($newFullName !== null) {
        $currentUser->fullName = $newFullName;
      }

      if ($newPassword !== null || $newPasswordConfirm !== null) {
        if (!$this->checkPasswordRequirements($newPassword, $newPasswordConfirm)) {
          return false;
        } else {
          if (!password_verify($oldPassword, $currentUser->password)) {
            return $this->createError("Wrong password");
          }

          $currentUser->password = $this->hashPassword($newPassword);
        }
      }

      $this->success = $currentUser->save($sql) !== false;
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }

  class ImportGPG extends UserAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "pubkey" => new StringType("pubkey")
      ));
      $this->loginRequired = true;
      $this->forbidMethod("GET");
    }

    private function testKey(string $keyString) {
      $res = GpgKey::getKeyInfo($keyString);
      if (!$res["success"]) {
        return $this->createError($res["error"] ?? $res["msg"]);
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

      $currentUser = $this->context->getUser();
      $gpgKey = $currentUser->getGPG();
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

      $sql = $this->context->getSQL();
      $gpgKey = new GpgKey($keyData["fingerprint"], $keyData["algorithm"], $keyData["expires"]);
      if (!$gpgKey->save($sql)) {
        return $this->createError("Error creating gpg key: " . $sql->getLastError());
      }

      $token = generateRandomString(36);
      $userToken = new UserToken($currentUser, $token, UserToken::TYPE_GPG_CONFIRM, 1);
      if (!$userToken->save($sql)) {
        return $this->createError("Error saving user token: " . $sql->getLastError());
      }

      $name = htmlspecialchars($currentUser->getFullName());
      if (!$name) {
        $name = htmlspecialchars($currentUser->getUsername());
      }

      $settings = $this->context->getSettings();
      $baseUrl = htmlspecialchars($settings->getBaseUrl());
      $token = htmlspecialchars(urlencode($token));
      $url = "$baseUrl/settings?confirmGPG&token=$token"; // TODO: fix this url
      $mailBody = "Hello $name,<br><br>" .
        "you imported a GPG public key for end-to-end encrypted mail communication. " .
        "To confirm the key and verify, you own the corresponding private key, please click on the following link. " .
        "The link is active for one hour.<br><br>" .
        "<a href='$url'>$url</a><br>
        Best Regards<br>" .
      $settings->getSiteName() . " Administration";

      $sendMail = new \Core\API\Mail\Send($this->context);
      $this->success = $sendMail->execute(array(
        "to" => $currentUser->getEmail(),
        "subject" => $settings->getSiteName() . " - Confirm GPG-Key",
        "body" => $mailBody,
        "gpgFingerprint" => $gpgKey->getFingerprint()
      ));

      $this->lastError = $sendMail->getLastError();

      if ($this->success) {
        $currentUser->gpgKey = $gpgKey;
        if ($currentUser->save($sql)) {
          $this->result["gpg"] = $gpgKey->jsonSerialize();
        } else {
          return $this->createError("Error updating user details: " . $sql->getLastError());
        }
      }

      return $this->success;
    }
  }

  class RemoveGPG extends UserAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "password" => new StringType("password")
      ));
      $this->loginRequired = true;
      $this->forbidMethod("GET");
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      $gpgKey = $currentUser->getGPG();
      if (!$gpgKey) {
        return $this->createError("You have not added a GPG public key to your account yet.");
      }

      $sql = $this->context->getSQL();
      $password = $this->getParam("password");
      if (!password_verify($password, $currentUser->password)) {
        return $this->createError("Incorrect password.");
      } else if (!$gpgKey->delete($sql)) {
        return $this->createError("Error deleting gpg key: " . $sql->getLastError());
      }

      return $this->success;
    }
  }

  class ConfirmGPG extends UserAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "token" => new StringType("token", 36)
      ]);
      $this->loginRequired = true;
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      $gpgKey = $currentUser->getGPG();
      if (!$gpgKey) {
        return $this->createError("You have not added a GPG key yet.");
      } else if ($gpgKey->isConfirmed()) {
        return $this->createError("Your GPG key is already confirmed");
      }

      $token = $this->getParam("token");
      $sql = $this->context->getSQL();

      $userToken = UserToken::findBuilder($sql)
        ->where(new Compare("token", $token))
        ->where(new Compare("valid_until", $sql->now(), ">="))
        ->where(new Compare("user_id", $currentUser->getId()))
        ->where(new Compare("token_type", UserToken::TYPE_GPG_CONFIRM))
        ->execute();

      if ($userToken !== false) {
        if ($userToken === null) {
          return $this->createError("Invalid token");
        } else {
          if (!$gpgKey->confirm($sql)) {
            return $this->createError("Error updating gpg key: " . $sql->getLastError());
          }

          $userToken->invalidate($sql);
        }
      } else {
        return $this->createError("Error validating token: " . $sql->getLastError());
      }

      return $this->success;
    }
  }

  class DownloadGPG extends UserAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
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

      $currentUser = $this->context->getUser();
      $userId = $this->getParam("id");
      if ($userId === null || $userId == $currentUser->getId()) {
        $gpgKey = $currentUser->getGPG();
        if (!$gpgKey) {
          return $this->createError("You did not add a gpg key yet.");
        }

        $email = $currentUser->getEmail();
      } else {
        $sql = $this->context->getSQL();
        $user = User::find($sql, $userId, true);
        if ($user === false) {
          return $this->createError("Error fetching user details: " . $sql->getLastError());
        } else if ($user === null) {
          return $this->createError("User not found");
        }

        $email = $user->getEmail();
        $gpgKey = $user->getGPG();
        if (!$gpgKey || !$gpgKey->isConfirmed()) {
          return $this->createError("This user has not added a gpg key yet or has not confirmed it yet.");
        }
      }

      $res = GpgKey::export($gpgKey->getFingerprint(), $format !== "gpg");
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
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
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

      $currentUser = $this->context->getUser();
      $userId = $currentUser->getId();
      $uploadDir = WEBROOT . "/img/uploads/user/$userId";
      list ($fileName, $imageName) = $this->processImageUpload($uploadDir, ["png", "jpg", "jpeg"], "onTransform");
      if (!$this->success) {
        return false;
      }

      $oldPfp = $currentUser->getProfilePicture();
      if ($oldPfp) {
        $path = "$uploadDir/$oldPfp";
        if (is_file($path)) {
          @unlink($path);
        }
      }

      $sql = $this->context->getSQL();
      $currentUser->profilePicture = $fileName;
      if ($currentUser->save($sql)) {
        $this->result["profilePicture"] = $fileName;
      } else {
        return $this->createError("Error updating user details: " . $sql->getLastError());
      }

      return $this->success;
    }
  }

  class RemovePicture extends UserAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, []);
      $this->loginRequired = true;
    }

    public function _execute(): bool {

      $sql = $this->context->getSQL();
      $currentUser = $this->context->getUser();
      $userId = $currentUser->getId();
      $pfp = $currentUser->getProfilePicture();
      if (!$pfp) {
        return $this->createError("You did not upload a profile picture yet");
      }

      $currentUser->profilePicture = null;
      if (!$currentUser->save($sql)) {
        return $this->createError("Error updating user details: " . $sql->getLastError());
      }

      $path = WEBROOT . "/img/uploads/user/$userId/$pfp";
      if (is_file($path)) {
        @unlink($path);
      }

      return $this->success;
    }
  }
}