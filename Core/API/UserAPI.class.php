<?php

namespace Core\API {

  use Core\Driver\SQL\Column\Column;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Condition\CondIn;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Group;
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

    protected function checkGroups(array &$groups): bool {
      $sql = $this->context->getSQL();
      $currentUser = $this->context->getUser();
      $requestedGroups = array_unique($this->getParam("groups"));
      if (!empty($requestedGroups)) {
        $availableGroups = Group::findAll($sql, new CondIn(new Column("id"), $requestedGroups));
        foreach ($requestedGroups as $groupId) {
          if (!isset($availableGroups[$groupId])) {
            return $this->createError("Group with id=$groupId does not exist.");
          } else if ($this->isExternalCall() && $groupId === Group::ADMIN && !$currentUser->hasGroup(Group::ADMIN)) {
            return $this->createError("You cannot create users with administrator groups.");
          } else {
            $groups[] = $groupId;
          }
        }
      }

      return true;
    }

    protected function insertUser(string $username, ?string $email, string $password, bool $confirmed, string $fullName = "", array $groups = []): bool|User {
      $sql = $this->context->getSQL();

      $user = new User();
      $user->language = Language::DEFAULT_LANGUAGE();
      $user->registeredAt = new \DateTime();
      $user->password = $this->hashPassword($password);
      $user->name = $username;
      $user->email = $email;
      $user->confirmed = $confirmed;
      $user->fullName = $fullName ?? "";
      $user->groups = $groups;

      $this->success = ($user->save($sql) !== FALSE);
      $this->lastError = $sql->getLastError();

      return $this->success ? $user : false;
    }

    protected function hashPassword($password): string {
      return password_hash($password, PASSWORD_BCRYPT);
    }

    protected function checkToken(string $token) : UserToken|bool {
      $sql = $this->context->getSQL();
      $userToken = UserToken::findBy(UserToken::createBuilder($sql, true)
        ->whereEq("UserToken.token", hash("sha512", $token, false))
        ->whereGt("UserToken.valid_until", $sql->now())
        ->whereFalse("UserToken.used")
        ->fetchEntities());

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

  use Core\API\Parameter\ArrayType;
  use Core\API\Parameter\FloatType;
  use Core\API\Parameter\IntegerType;
  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\API\Request;
  use Core\API\Template\Render;
  use Core\API\Traits\Captcha;
  use Core\API\Traits\Pagination;
  use Core\API\UserAPI;
  use Core\Driver\SQL\Condition\CondBool;
  use Core\Driver\SQL\Condition\CondLike;
  use Core\Driver\SQL\Condition\CondOr;
  use Core\Driver\SQL\Expression\Alias;
  use Core\Objects\DatabaseEntity\Group;
  use Core\Objects\DatabaseEntity\Session;
  use Core\Objects\DatabaseEntity\UserToken;
  use Core\Driver\SQL\Column\Column;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Condition\CondIn;
  use Core\Driver\SQL\Expression\JsonArrayAgg;
  use Core\Objects\RateLimiting;
  use Core\Objects\RateLimitRule;
  use Core\Objects\TwoFactor\KeyBasedTwoFactorToken;
  use ImagickException;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\User;

  class Create extends UserAPI {

    private User $user;

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'username' => new StringType('username', 32),
        'fullName' => new StringType('fullName', 64, true, ""),
        'email' => new Parameter('email', Parameter::TYPE_EMAIL, true, NULL),
        'password' => new StringType('password'),
        'confirmPassword' => new StringType('confirmPassword'),
        'groups' => new ArrayType("groups", Parameter::TYPE_INT, true, true, [])
      ));

      $this->loginRequirements = Request::LOGGED_IN;
    }

    public function _execute(): bool {

      $username = $this->getParam('username');
      $fullName = $this->getParam('fullName');
      $email = $this->getParam('email');
      $password = $this->getParam('password');
      $confirmPassword = $this->getParam('confirmPassword');
      if (!$this->checkRequirements($username, $password, $confirmPassword)) {
        return false;
      }

      $groups = [];
      if (!$this->checkGroups($groups)) {
        return false;
      }

      if (!$this->checkUserExists($username, $email)) {
        return false;
      }

      // prevent duplicate keys
      $email = (!is_null($email) && empty($email)) ? null : $email;
      $user = $this->insertUser($username, $email, $password, true, $fullName, $groups);
      if ($user !== false) {
        $this->user = $user;
        $this->result["userId"] = $user->getId();
        $this->logger->info("A new user with username='$username' and email='$email' was created by " . $this->logUserId());
      }

      return $this->success;
    }

    public function getUser(): User {
      return $this->user;
    }

    public static function getDescription(): string {
      return "Allows users to create new users";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN];
    }
  }

  class Fetch extends UserAPI {

    use Pagination;

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall,
        self::getPaginationParameters(['id', 'name', 'fullName', 'email', 'groups', 'lastOnline', 'registeredAt', 'active', 'confirmed'],
          'id', 'asc')
      );
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      $fullInfo = ($currentUser->hasGroup(Group::ADMIN) ||
        $currentUser->hasGroup(Group::SUPPORT));

      $orderBy = $this->getParam("orderBy");

      $condition = null;
      if (!$fullInfo) {
        $condition = new CondOr(
          new Compare("User.id", $currentUser->getId()),
          new CondBool("User.confirmed")
        );

        if ($orderBy && !$currentUser->canAccess(User::class, $orderBy)) {
          return $this->createError("Insufficient permissions for sorting by field '$orderBy'");
        }
      }

      $sql = $this->context->getSQL();
      if (!$this->initPagination($sql, User::class, $condition)) {
        return false;
      }

      $groupNames = new Alias(
        $sql->select(new JsonArrayAgg("name"))->from("Group")
          ->leftJoin("NM_User_groups", "NM_User_groups.group_id", "Group.id")
          ->whereEq("NM_User_groups.user_id", new Column("User.id")),
        "groups"
      );

      $userQuery = $this->createPaginationQuery($sql, [$groupNames]);
      $users = User::findBy($userQuery);
      if ($users !== false && $users !== null) {
        $this->result["users"] = [];
        foreach ($users as $user) {
          $this->result["users"][] = $user->jsonSerialize();
        }
      } else {
        return $this->createError("Error fetching users: " . $sql->getLastError());
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to fetch all users";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN, Group::SUPPORT];
    }
  }

  class Get extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'id' => new Parameter('id', Parameter::TYPE_INT)
      ));
      $this->loginRequirements = Request::LOGGED_IN;
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
        // allow access to unconfirmed users only when we have administrative privileges, or we are querying ourselves
        $currentUser = $this->context->getUser();
        $fullInfo = ($userId === $currentUser->getId() ||
          $currentUser->hasGroup(Group::ADMIN) ||
          $currentUser->hasGroup(Group::SUPPORT));

        if (!$fullInfo && !$user->isConfirmed()) {
          return $this->createError("No permissions to access this user");
        }

        $this->result["user"] = $user->jsonSerialize();
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to get details about a user";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN, Group::SUPPORT];
    }
  }

  class Search extends UserAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "query" => new StringType("query", 64)
      ]);
    }

    protected function _execute(): bool {
      $sql = $this->context->getSQL();
      $query = $this->getParam("query");

      $users = User::findBy(User::createBuilder($sql, false)
        ->where(new CondOr(
          new CondLike(new Column("name"), "%$query%"),
          new CondLike(new Column("full_name"), "%$query%"),
          new CondLike(new Column("email"), "%$query%"),
        ))
        ->whereTrue("active")
      );

      if ($users === false) {
        return $this->createError($sql->getLastError());
      }

      $this->result["users"] = $users;
      return true;
    }

    public static function getDescription(): string {
      return "Allows users to search other users";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN, Group::SUPPORT];
    }
  }

  class Info extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array());
      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      $language = $this->context->getLanguage();
      $this->result["language"] = $language->jsonSerialize();

      if (!$currentUser) {
        $this->result["loggedIn"] = false;
        $userGroups = [];
      } else {

        $twoFactorToken = $currentUser->getTwoFactorToken();
        if ($twoFactorToken instanceof KeyBasedTwoFactorToken && !$twoFactorToken->hasChallenge()) {
          $twoFactorToken->generateChallenge();
        }

        $this->result["loggedIn"] = true;
        $userGroups = array_keys($currentUser->getGroups());
        $this->result["user"] = $currentUser->jsonSerialize();
        $this->result["session"] = $this->context->getSession()->jsonSerialize([
          "id", "expires", "stayLoggedIn", "data", "csrfToken"
        ]);
      }

      $sql = $this->context->getSQL();
      $res = $sql->select("method", "groups")
        ->from("ApiPermission")
        ->execute();

      $this->result["permissions"] = [];
      if (is_array($res)) {
        foreach ($res as $row) {
          $requiredGroups = json_decode($row["groups"], true);
          if (empty($requiredGroups) || !empty(array_intersect($requiredGroups, $userGroups))) {
            $this->result["permissions"][] = $row["method"];
          }
        }
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Retrieves information about the current session";
    }

    public static function hasConfigurablePermissions(): bool {
      return false;
    }
  }

  class Invite extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'username' => new StringType('username', 32),
        'fullName' => new StringType('fullName', 64, true, ""),
        'email' => new StringType('email', 64),
        'groups' => new ArrayType("groups", Parameter::TYPE_INT, true, true, [])
      ));

      $this->loginRequirements = Request::LOGGED_IN;
    }

    public function _execute(): bool {

      $sql = $this->context->getSQL();
      $settings = $this->context->getSettings();
      $currentUser = $this->context->getUser();
      if (!$settings->isMailEnabled()) {
        return $this->createError("An invitation cannot be sent because mailing is not enabled.");
      }

      $username = $this->getParam('username');
      $fullName = $this->getParam('fullName');
      $email = $this->getParam('email');
      $groups = [];

      if (!$this->checkGroups($groups)) {
        return false;
      }

      if (!$this->checkUserExists($username, $email)) {
        return false;
      }

      // Create user
      $user = $this->insertUser($username, $email, "", false, $fullName, $groups);
      if ($user === false) {
        return false;
      }

      $this->result["userId"] = $user->getId();
      $this->logger->info("A new user with username='$username' and email='$email' was invited by " . $this->logUserId());

      // Create Token
      $token = generateRandomString(36);
      $validDays = 7;
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

    public static function getDescription(): string {
      return "Allows users to invite new users";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN, Group::SUPPORT, Group::MODERATOR];
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
      $this->loginRequirements = Request::NOT_LOGGED_IN;
    }

    public function _execute(): bool {
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
        if ($user->save($sql, ["password", "confirmed"])) {
          $userToken->invalidate($sql);
          return true;
        } else {
          return $this->createError("Unable to update user details: " . $sql->getLastError());
        }
      }
    }

    public static function getDescription(): string {
      return "Allows users to accept invitations and register an account";
    }
  }

  class ConfirmEmail extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'token' => new StringType('token', 36)
      ));
      $this->csrfTokenRequired = false;
      $this->loginRequirements = Request::NOT_LOGGED_IN;
      $this->rateLimiting = new RateLimiting(
        new RateLimitRule(5, 1, RateLimitRule::MINUTE)
      );
    }

    public function _execute(): bool {
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
        if ($user->save($sql, ["confirmed"])) {
          $userToken->invalidate($sql);
          return true;
        } else {
          return $this->createError("Unable to update user details: " . $sql->getLastError());
        }
      }
    }

    public static function getDescription(): string {
      return "Allows users to confirm their email";
    }
  }

  class Login extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'username' => new StringType('username'),
        'password' => new StringType('password'),
        'stayLoggedIn' => new Parameter('stayLoggedIn', Parameter::TYPE_BOOLEAN, true, false)
      ));
      $this->forbidMethod("GET");
      $this->rateLimiting = new RateLimiting(
        new RateLimitRule(10, 30, RateLimitRule::SECOND)
      );
    }

    public function _execute(): bool {

      if ($this->context->getUser()) {
        $this->lastError = L('You are already logged in');
        $this->success = true;

        $tfaToken = $this->context->getUser()->getTwoFactorToken();
        if ($tfaToken && $tfaToken->isConfirmed() && !$tfaToken->isAuthenticated()) {
          $this->result["twoFactorToken"] = $tfaToken->jsonSerialize([
            "type", "challenge", "authenticated", "confirmed", "credentialID"
          ]);
        }

        return true;
      }

      $this->success = false;
      $username = $this->getParam('username');
      $password = $this->getParam('password');
      $stayLoggedIn = $this->getParam('stayLoggedIn');

      $sql = $this->context->getSQL();
      $user = User::findBy(User::createBuilder($sql, true)
        ->where(new Compare("User.name", $username), new Compare("User.email", $username))
        ->whereEq("User.sso_provider", NULL)
        ->fetchEntities());

      if ($user !== false) {
        if ($user === null) {
          return $this->createError(L('Wrong username or password'));
        } else if (!$user->isActive()) {
          return $this->createError("This user is currently disabled. Contact the server administrator, if you believe this is a mistake.");
        } else if (password_verify($password, $user->password)) {
          if (!$user->confirmed) {
            $this->result["emailConfirmed"] = false;
            return $this->createError("Your email address has not been confirmed yet.");
          } else {
            return $this->createSession($user, $stayLoggedIn);
          }
        } else {
          return $this->createError(L('Wrong username or password'));
        }
      } else {
        return $this->createError("Error fetching user details: " . $sql->getLastError());
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Creates a new session identified by the session cookie";
    }

    public static function hasConfigurablePermissions(): bool {
      return false;
    }
  }

  class Logout extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall);
      $this->apiKeyAllowed = false;
      $this->forbidMethod("GET");
    }

    public function _execute(): bool {

      $session = $this->context->getSession();
      if (!$session) {
        return $this->createError("You are not logged in.");
      }

      session_destroy();
      $this->success = $session->destroy();
      $this->lastError = $this->context->getSQL()->getLastError();
      return $this->success;
    }

    public static function getDescription(): string {
      return "Destroys the current session and logs the user out";
    }

    public static function hasConfigurablePermissions(): bool {
      return false;
    }
  }

  class Register extends UserAPI {

    use Captcha;

    public function __construct(Context $context, bool $externalCall = false) {
      $parameters = array(
        "username" => new StringType("username", 32),
        "email" => new Parameter("email", Parameter::TYPE_EMAIL),
        "password" => new StringType("password"),
        "confirmPassword" => new StringType("confirmPassword"),
      );

      $this->addCaptchaParameters($context, $parameters);
      parent::__construct($context, $externalCall, $parameters);
      $this->csrfTokenRequired = false;
      $this->loginRequirements = Request::NOT_LOGGED_IN;
    }

    public function _execute(): bool {
      $settings = $this->context->getSettings();
      $registrationAllowed = $settings->isRegistrationAllowed();
      if (!$registrationAllowed) {
        return $this->createError("User Registration is not enabled.");
      }

      if (!$this->checkCaptcha("register")) {
        return false;
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

      $this->logger->info("A new user with username='$username' and email='$email' was created");

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
        $this->lastError = "Could not create user token: " . $sql->getLastError();
        $this->success = false;
      }

      if (!$this->success) {
        $this->logger->error("Could not deliver email to='$email' type='register' reason='" . $this->lastError . "'");
        $this->lastError = "Your account was registered but the confirmation email could not be sent. " .
          "Please contact the server administration. This issue has been automatically logged. Reason: " . $this->lastError;
      }

      $this->logger->info("Registered new user with id=" . $user->getId());
      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to register a new account";
    }
  }

  class Edit extends UserAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        'id' => new Parameter('id', Parameter::TYPE_INT),
        'username' => new StringType('username', 32, true, NULL),
        'fullName' => new StringType('fullName', 64, true, NULL),
        'email' => new Parameter('email', Parameter::TYPE_EMAIL, true, NULL),
        'password' => new StringType('password', -1, true, NULL),
        'groups' => new ArrayType('groups', Parameter::TYPE_INT, true, true, NULL),
        'confirmed' => new Parameter('confirmed', Parameter::TYPE_BOOLEAN, true, NULL),
        'active' => new Parameter('active', Parameter::TYPE_BOOLEAN, true, NULL)
      ]);

      $this->forbidMethod("GET");
      $this->loginRequirements = Request::LOGGED_IN;
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

        $columnsToUpdate = [];
        $username = $this->getParam("username");
        $fullName = $this->getParam("fullName");
        $email = $this->getParam("email");
        $password = $this->getParam("password");
        $groups = $this->getParam("groups");
        $confirmed = $this->getParam("confirmed");
        $active = $this->getParam("active");
        $email = (!is_null($email) && empty($email)) ? null : $email;

        if (!is_null($groups)) {
          $groupIds = array_unique($groups);
          if ($id === $currentUser->getId() && !in_array(Group::ADMIN, $groupIds)) {
            return $this->createError("Cannot remove Administrator group from own user.");
          } else if (in_array(Group::ADMIN, $groupIds) && !$currentUser->hasGroup(Group::ADMIN)) {
            return $this->createError("You cannot add the administrator group to other users.");
          }

          $availableGroups = Group::findAll($sql, new CondIn(new Column("id"), $groupIds));
          foreach ($groupIds as $groupId) {
            if (!isset($availableGroups[$groupId])) {
              return $this->createError("Group with id=$groupId does not exist.");
            }
          }

          $user->groups = $groupIds;
          $columnsToUpdate[] = "groups";
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

        if ($usernameChanged) {
          $user->name = $username;
          $columnsToUpdate[] = "name";
        }

        if ($fullNameChanged) {
          $user->fullName = $fullName;
          $columnsToUpdate[] = "fullName";
        }

        if ($emailChanged) {
          $user->email = $email;
          $columnsToUpdate[] = "email";
        }

        if (!is_null($password)) {
          $user->password = $this->hashPassword($password);
          $columnsToUpdate[] = "password";
        }

        if (!is_null($confirmed)) {
          if ($id === $currentUser->getId() && $confirmed === false) {
            return $this->createError("Cannot change confirmed flag on own account.");
          } else {
            $user->confirmed = $confirmed;
            $columnsToUpdate[] = "confirmed";
          }
        }

        if (!is_null($active)) {
          if ($id === $currentUser->getId() && $active === false) {
            return $this->createError("Cannot change active flag on own account.");
          } else {
            $user->active = $active;
            $columnsToUpdate[] = "active";
          }
        }

        if (!empty($columnsToUpdate)) {
          $this->success = $user->save($sql, $columnsToUpdate, in_array("groups", $columnsToUpdate)) !== FALSE;
          $this->lastError = $sql->getLastError();
        }

      } else {
        return $this->createError("Error fetching user details: " . $sql->getLastError());
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to modify other user's details";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN];
    }
  }

  class Delete extends UserAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'id' => new Parameter('id', Parameter::TYPE_INT)
      ));

      $this->loginRequirements = Request::LOGGED_IN;
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
          $this->logger->info(sprintf(
            "User '%s' (id=%d) deleted by %s",
            $user->getDisplayName(),
            $id,
            $this->logUserId())
          );
        }
      } else {
        $this->lastError = $sql->getLastError();
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to delete other users";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN];
    }
  }

  class RequestPasswordReset extends UserAPI {

    use Captcha;

    public function __construct(Context $context, $externalCall = false) {
      $parameters = [
        'email' => new Parameter('email', Parameter::TYPE_EMAIL),
      ];

      $this->addCaptchaParameters($context, $parameters);
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

      if (!$this->checkCaptcha("resetPassword")) {
        return false;
      }

      $sql = $this->context->getSQL();
      $email = $this->getParam("email");
      $user = User::findBy(User::createBuilder($sql, true)
        ->whereEq("email", $email)
        ->fetchEntities());
      if ($user === false) {
        return $this->createError("Could not fetch user details: " . $sql->getLastError());
      } else if ($user !== null) {
        if (!$user->isActive()) {
          return $this->createError("This user is currently disabled. Contact the server administrator, if you believe this is a mistake.");
        } else if (!$user->isLocalAccount()) {
          // TODO: this allows user enumeration for SSO accounts
          return $this->createError("Cannot request a password reset: Account is managed by an external identity provider (SSO)");
        } else {
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
            $this->logger->info("Requested password reset for user id='" . $user->getId() . "' by ip_address='" . $_SERVER["REMOTE_ADDR"] . "'");
          }
        }
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to request a password reset link";
    }
  }

  class ResendConfirmEmail extends UserAPI {

    use Captcha;

    public function __construct(Context $context, $externalCall = false) {
      $parameters = array(
        'email' => new Parameter('email', Parameter::TYPE_EMAIL),
      );

      $this->addCaptchaParameters($context, $parameters);
      parent::__construct($context, $externalCall, $parameters);
    }

    public function _execute(): bool {

      if ($this->context->getUser()) {
        return $this->createError("You already logged in.");
      }

      $settings = $this->context->getSettings();
      if (!$this->checkCaptcha("resendConfirmation")) {
        return false;
      }

      $email = $this->getParam("email");
      $sql = $this->context->getSQL();
      $user = User::findBy(User::createBuilder($sql, true)
        ->whereEq("User.email", $email)
        ->whereFalse("User.confirmed"));

      if ($user === false) {
        return $this->createError("Error retrieving user details: " . $sql->getLastError());
      } else if ($user === null) {
        // token does not exist: ignore!
        return true;
      }

      $validHours = 48;
      $token = generateRandomString(36);
      $userToken = new UserToken($user, $token, UserToken::TYPE_EMAIL_CONFIRM, $validHours);
      if (!$userToken->save($sql)) {
        return $this->createError("Error generating new token: " . $sql->getLastError());
      }

      $username = $user->name;
      $baseUrl = $settings->getBaseUrl();
      $siteName = $settings->getSiteName();

      $req = new Render($this->context);
      $this->success = $req->execute([
        "file" => "mail/confirm_email.twig",
        "parameters" => [
          "link" => "$baseUrl/confirmEmail?token=" . $token,
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

    public static function getDescription(): string {
      return "Allows users to request a new e-mail confirmation link";
    }
  }

  class ResetPassword extends UserAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, [
        'token' => new StringType('token', 36),
        'password' => new StringType('password'),
        'confirmPassword' => new StringType('confirmPassword'),
      ]);

      $this->forbidMethod("GET");
      $this->csrfTokenRequired = false;
      $this->apiKeyAllowed = false;
      $this->loginRequirements = Request::NOT_LOGGED_IN;
      $this->rateLimiting = new RateLimiting(
        new RateLimitRule(5, 1, RateLimitRule::MINUTE)
      );
    }

    public function _execute(): bool {
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

      $user = $userToken->getUser();
      if (!$user->isLocalAccount()) {
        return $this->createError("Cannot reset password: Your account is managed by an external identity provider (SSO)");
      } else if (!$this->checkPasswordRequirements($password, $confirmPassword)) {
        return false;
      } else {
        $user->password = $this->hashPassword($password);
        if ($user->save($sql)) {
          $this->logger->info("Issued password reset for user id=" . $user->getId());
          $userToken->invalidate($sql);
          $this->context->invalidateSessions(false);
          return true;
        } else {
          return $this->createError("Error updating user details: " . $sql->getLastError());
        }
      }
    }

    public static function getDescription(): string {
      return "Allows users to reset their password with a token received by a password reset email";
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
      $this->loginRequirements = Request::LOGGED_IN;
      $this->csrfTokenRequired = true;
      $this->apiKeyAllowed = false; // prevent account takeover when an API-key is stolen
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
      $updateFields = [];

      $currentUser = $this->context->getUser();
      if ($newUsername !== null) {
        if (!$this->checkUsernameRequirements($newUsername) || !$this->checkUserExists($newUsername)) {
          return false;
        } else {
          $currentUser->name = $newUsername;
          $updateFields[] = "name";
        }
      }

      if ($newFullName !== null) {
        $currentUser->fullName = $newFullName;
        $updateFields[] = "fullName";
      }

      if ($newPassword !== null || $newPasswordConfirm !== null) {
        if (!$currentUser->isLocalAccount()) {
          return $this->createError("Cannot change password: Your account is managed by an external identity provider (SSO)");
        } else if (!$this->checkPasswordRequirements($newPassword, $newPasswordConfirm)) {
          return false;
        } else {
          if (!password_verify($oldPassword, $currentUser->password)) {
            return $this->createError("Wrong password");
          }

          $currentUser->password = $this->hashPassword($newPassword);
          $updateFields[] = "password";
        }
      }

      if (!empty($updateFields)) {
        $this->success = $currentUser->save($sql, $updateFields) !== false;
        $this->lastError = $sql->getLastError();
        if ($this->success && in_array("password", $updateFields)) {
          $this->context->invalidateSessions(true);
        }
      }
      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to update their profiles.";
    }
  }

  class UploadPicture extends UserAPI {

    const MIN_SIZE = 150;
    const MAX_SIZE = 800;

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "x" => new FloatType("x", 0, PHP_FLOAT_MAX, true, NULL),
        "y" => new FloatType("y", 0, PHP_FLOAT_MAX, true, NULL),
        "size" => new FloatType("size", self::MIN_SIZE, self::MAX_SIZE, true, NULL),
      ]);
      $this->loginRequirements = Request::LOGGED_IN;
      $this->forbidMethod("GET");
    }

    /**
     * @throws ImagickException
     */
    protected function onTransform(\Imagick $im, $uploadDir): bool|string {

      $width = $im->getImageWidth();
      $height = $im->getImageHeight();
      $maxPossibleSize = min($width, $height);

      $cropX = $this->getParam("x");
      $cropY = $this->getParam("y");
      $cropSize = $this->getParam("size") ?? $maxPossibleSize;

      if ($maxPossibleSize < self::MIN_SIZE) {
        return $this->createError("Image must be at least " . self::MIN_SIZE . "x" . self::MIN_SIZE);
      } else if ($cropSize > self::MAX_SIZE) {
        return $this->createError("Crop must be at most " . self::MAX_SIZE . "x" . self::MAX_SIZE);
      } else if ($cropSize > $maxPossibleSize) {
        return $this->createError("Invalid crop size");
      }

      if ($cropX === null) {
        $cropX = ($width > $height) ? ($width - $height) / 2 : 0;
      }

      if ($cropY === null) {
        $cropY = ($height > $width) ? ($height - $width) / 2 : 0;
      }

      $im->cropImage($cropSize, $cropSize, $cropX, $cropY);
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
      if ($oldPfp && preg_match("/[a-fA-F0-9-]+\.(jpg|jpeg|png|gif)/", $oldPfp)) {
        $path = "$uploadDir/$oldPfp";
        if (is_file($path)) {
          @unlink($path);
        }
      }

      $sql = $this->context->getSQL();
      $currentUser->profilePicture = $fileName;
      if ($currentUser->save($sql, ["profilePicture"])) {
        $this->result["profilePicture"] = $fileName;
      } else {
        return $this->createError("Error updating user details: " . $sql->getLastError());
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to upload and change their profile pictures.";
    }
  }

  class RemovePicture extends UserAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, []);
      $this->loginRequirements = Request::LOGGED_IN;
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
      if (!$currentUser->save($sql, ["profilePicture"])) {
        return $this->createError("Error updating user details: " . $sql->getLastError());
      }

      if (preg_match("/[a-fA-F0-9-]+\.(jpg|jpeg|png|gif)/", $pfp)) {
        $path = WEBROOT . "/img/uploads/user/$userId/$pfp";
        if (is_file($path)) {
          @unlink($path);
        }
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to remove their profile pictures.";
    }
  }

  class CheckToken extends UserAPI {

    private ?UserToken $userToken;

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'token' => new StringType('token', 36),
      ));
      $this->userToken = null;
      $this->rateLimiting = new RateLimiting(
        new RateLimitRule(10, 30, RateLimitRule::SECOND),
        new RateLimitRule(30, 1, RateLimitRule::MINUTE),
      );
    }

    public function getToken(): ?UserToken {
      return $this->userToken;
    }

    public function _execute(): bool {

      $token = $this->getParam('token');
      $userToken = $this->checkToken($token);
      if ($userToken === false) {
        return false;
      }

      $this->userToken = $userToken;
      $this->result["token"] = $userToken->jsonSerialize();
      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to validate a token received in an e-mail for various purposes";
    }
  }

  class GetSessions extends UserAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "active" => new Parameter("active", Parameter::TYPE_BOOLEAN, true, true)
      ]);
      $this->loginRequirements = Request::LOGGED_IN;
    }

    protected function _execute(): bool {

      $sql = $this->context->getSQL();
      $currentUser = $this->context->getUser();
      $activeOnly = $this->getParam("active");

      $query = Session::createBuilder($sql, false)
        ->whereEq("user_id", $currentUser->getId());

      if ($activeOnly) {
        $query->whereTrue("active")
          ->whereGt("expires", $sql->now());
      }

      $sessions = Session::findBy($query);
      if ($sessions === false) {
        return $this->createError("Error fetching sessions:" . $sql->getLastError());
      }

      $this->result["sessions"] = Session::toJsonArray($sessions, [
        "id", "expires", "ipAddress", "os", "browser", "lastOnline"
      ]);

      return true;
    }


    public static function getDescription(): string {
      return "Shows logged-in sessions for a users account";
    }

    public static function getDefaultPermittedGroups(): array {
      return [];
    }
  }

  class DestroySession extends UserAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "id" => new Parameter("id", Parameter::TYPE_INT)
      ]);
      $this->loginRequirements = Request::LOGGED_IN;
    }

    protected function _execute(): bool {
      $sql = $this->context->getSQL();
      $id = $this->getParam("id");
      $currentUser = $this->context->getUser();

      $query = Session::createBuilder($sql, true)
        ->whereEq("id", $id)
        ->whereEq("user_id", $currentUser->getId());

      $session = Session::findBy($query);
      if ($session === false) {
        return $this->createError("Error fetching session:" . $sql->getLastError());
      } else if ($session === null) {
        return $this->createError("Invalid session");
      }

      $session->destroy();
      if ($session->getId() === $this->context->getSession()->getId()) {
        session_destroy();
      }

      return true;
    }

    public static function getDescription(): string {
      return "Terminates a given user session";
    }

    public static function getDefaultPermittedGroups(): array {
      return [];
    }
  }
}