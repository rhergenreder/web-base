<?php

namespace Core\API {

  use Core\Objects\Context;

  abstract class GpgKeyAPI extends \Core\API\Request {
    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
      $this->loginRequired = true;
    }
  }

}

namespace Core\API\GpgKey {


  use Core\API\GpgKeyAPI;
  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\API\Template\Render;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\GpgKey;
  use Core\Objects\DatabaseEntity\User;
  use Core\Objects\DatabaseEntity\UserToken;

  class Import extends GpgKeyAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "pubkey" => new StringType("pubkey")
      ]);
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
      } else if (!$currentUser->getEmail()) {
        return $this->createError("You do not have an e-mail address");
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

      $validHours = 1;
      $settings = $this->context->getSettings();
      $baseUrl = $settings->getBaseUrl();
      $siteName = $settings->getSiteName();
      $req = new Render($this->context);
      $this->success = $req->execute([
        "file" => "mail/gpg_import.twig",
        "parameters" => [
          "link" => "$baseUrl/confirmGPG?token=$token",
          "site_name" => $siteName,
          "base_url" => $baseUrl,
          "username" => $currentUser->getDisplayName(),
          "valid_time" => $this->formatDuration($validHours, "hour")
        ]
      ]);

      $this->lastError = $req->getLastError();

      if ($this->success) {
        $messageBody = $req->getResult()["html"];
        $sendMail = new \Core\API\Mail\Send($this->context);
        $this->success = $sendMail->execute(array(
          "to" => $currentUser->getEmail(),
          "subject" => "[$siteName] Confirm GPG-Key",
          "body" => $messageBody,
          "gpgFingerprint" => $gpgKey->getFingerprint()
        ));

        $this->lastError = $sendMail->getLastError();

        if ($this->success) {
          $currentUser->gpgKey = $gpgKey;
          if ($currentUser->save($sql, ["gpgKey"])) {
            $this->result["gpgKey"] = $gpgKey->jsonSerialize();
          } else {
            return $this->createError("Error updating user details: " . $sql->getLastError());
          }
        }
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to import gpg keys for a secure e-mail communication";
    }
  }

  class Remove extends GpgKeyAPI {
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

    public static function getDescription(): string {
      return "Allows users to unlink gpg keys from their profile";
    }
  }

  class Confirm extends GpgKeyAPI {

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

      $userToken = UserToken::findBy(UserToken::createBuilder($sql, true)
        ->whereEq("token", $token)
        ->where(new Compare("valid_until", $sql->now(), ">="))
        ->whereEq("user_id", $currentUser->getId())
        ->whereEq("token_type", UserToken::TYPE_GPG_CONFIRM));

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

    public static function getDescription(): string {
      return "Allows users to confirm their imported gpg key";
    }
  }

  class Download extends GpgKeyAPI {
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

    public static function getDescription(): string {
      return "Allows users to download any gpg public key";
    }
  }
}