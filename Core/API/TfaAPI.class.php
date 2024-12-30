<?php

namespace Core\API {

  use Core\Objects\Context;
  use Core\Objects\TwoFactor\AuthenticationData;
  use Core\Objects\TwoFactor\KeyBasedTwoFactorToken;

  abstract class TfaAPI extends Request {

    private bool $userVerificationRequired;

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
      $this->loginRequirements = Request::LOGGED_IN;
      $this->apiKeyAllowed = false;
      $this->userVerificationRequired = false;
    }

    protected function verifyAuthData(AuthenticationData $authData): bool {
      $domain = getCurrentHostName();
      if (!$authData->verifyIntegrity($domain)) {
        return $this->createError("mismatched rpIDHash. expected: " . hash("sha256", $domain) . " got: " . bin2hex($authData->getHash()));
      } else if (!$authData->isUserPresent()) {
        return $this->createError("No user present");
      } else if ($this->userVerificationRequired && !$authData->isUserVerified()) {
        return $this->createError("user was not verified on device (PIN/Biometric/...)");
      } else if ($authData->hasExtensionData()) {
        return $this->createError("No extensions supported");
      }

      return true;
    }

    protected function verifyClientDataJSON(array $jsonData, KeyBasedTwoFactorToken $token): bool {
      $settings = $this->context->getSettings();
      $expectedType = $token->isConfirmed() ? "webauthn.get" : "webauthn.create";
      $type = $jsonData["type"] ?? "null";
      if ($type !== $expectedType) {
        return $this->createError("Invalid client data json type. Expected: '$expectedType', Got: '$type'");
      } else if (base64url_decode($token->getChallenge()) !== base64url_decode($jsonData["challenge"] ?? "")) {
        return $this->createError("Challenge does not match");
      }

      $origin = $jsonData["origin"] ?? null;
      if ($origin !== $settings->getBaseURL()) {
        $baseUrl = $settings->getBaseURL();
       // return $this->createError("Origin does not match. Expected: '$baseUrl', Got: '$origin'");
      }

      return true;
    }
  }
}

namespace Core\API\TFA {

  use Core\API\Parameter\StringType;
  use Core\API\TfaAPI;
  use Core\Objects\Context;
  use Core\Objects\RateLimiting;
  use Core\Objects\RateLimitRule;
  use Core\Objects\TwoFactor\AttestationObject;
  use Core\Objects\TwoFactor\AuthenticationData;
  use Core\Objects\TwoFactor\KeyBasedTwoFactorToken;
  use Core\Objects\TwoFactor\TimeBasedTwoFactorToken;

  // General
  class Remove extends TfaAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "password" => new StringType("password", 0, true)
      ]);
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      $token = $currentUser->getTwoFactorToken();
      if (!$token) {
        return $this->createError("You do not have an active 2FA-Token");
      }

      $sql = $this->context->getSQL();
      $password = $this->getParam("password");
      if ($password) {
        if (!password_verify($password, $currentUser->password)) {
          return $this->createError("Wrong password");
        }
      } else if ($token->isConfirmed()) {
        // if the token is fully confirmed, require a password to remove it
        return $this->createError("Missing parameter: password");
      }
      $this->success = $token->delete($sql) !== false;
      $this->lastError = $sql->getLastError();

      if ($this->success && $token->isConfirmed()) {
        // send an email
        $email = $currentUser->getEmail();
        if ($email) {
          $settings = $this->context->getSettings();
          $req = new \Core\API\Template\Render($this->context);
          $this->success = $req->execute([
            "file" => "mail/2fa_remove.twig",
            "parameters" => [
              "username" => $currentUser->getFullName() ?? $currentUser->getUsername(),
              "site_name" => $settings->getSiteName(),
              "sender_mail" => $settings->getMailSender()
            ]
          ]);

          if ($this->success) {
            $body = $req->getResult()["html"];
            $gpg = $currentUser->getGPG();
            $siteName = $settings->getSiteName();
            $req = new \Core\API\Mail\Send($this->context);
            $this->success = $req->execute([
              "to" => $email,
              "subject" => "[$siteName] 2FA-Authentication removed",
              "body" => $body,
              "gpgFingerprint" => $gpg?->getFingerprint()
            ]);
          }

          $this->lastError = $req->getLastError();
        }
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to remove their 2FA-Tokens";
    }
  }

  // TOTP
  class GenerateQR extends TfaAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall);
      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      $twoFactorToken = $currentUser->getTwoFactorToken();
      if ($twoFactorToken && $twoFactorToken->isConfirmed()) {
        return $this->createError("You already added a two factor token");
      } else if (!$currentUser->isLocalAccount()) {
        return $this->createError("Cannot add a 2FA token: Your account is managed by an external identity provider (SSO)");
      } else if (!($twoFactorToken instanceof TimeBasedTwoFactorToken)) {
        $sql = $this->context->getSQL();
        $twoFactorToken = new TimeBasedTwoFactorToken(generateRandomString(32, "base32"));
        $this->success = $twoFactorToken->save($sql) !== false;
        $this->lastError = $sql->getLastError();
        if ($this->success) {
          $currentUser->setTwoFactorToken($twoFactorToken);
          $this->success = $currentUser->save($sql, ["twoFactorToken"]);
          $this->lastError = $sql->getLastError();
        }

        if (!$this->success) {
          return false;
        }
      }

      header("Content-Type: image/png");
      $this->disableCache();
      die($twoFactorToken->generateQRCode($this->context));
    }

    public static function getDescription(): string {
      return "Allows users generate a QR-code to add a time-based 2FA-Token";
    }
  }

  class ConfirmTotp extends VerifyTotp {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall);
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      $twoFactorToken = $currentUser->getTwoFactorToken();
      if ($twoFactorToken->isConfirmed()) {
        return $this->createError("Your two factor token is already confirmed.");
      }

      if (!parent::_execute()) {
        return false;
      }

      $sql = $this->context->getSQL();
      $this->success = $twoFactorToken->confirm($sql) !== false;
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->context->invalidateSessions(true);
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to confirm their time-based 2FA-Token";
    }
  }

  class VerifyTotp extends TfaAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "code" => new StringType("code", 6)
      ]);
      $this->csrfTokenRequired = false;
      $this->rateLimiting = new RateLimiting(
        null,
        new RateLimitRule(5, 30, RateLimitRule::SECOND)
      );
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      $twoFactorToken = $currentUser->getTwoFactorToken();
      if (!$twoFactorToken) {
        return $this->createError("You did not add a two factor token yet.");
      } else if (!($twoFactorToken instanceof TimeBasedTwoFactorToken)) {
        return $this->createError("Invalid 2FA-token endpoint");
      }

      $code = $this->getParam("code");
      if (!$twoFactorToken->verify($code)) {
        return $this->createError("Code does not match");
      }

      $twoFactorToken->authenticate();
      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to verify time-based 2FA-Tokens";
    }
  }

  // Key
  class RegisterKey extends TfaAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "clientDataJSON" => new StringType("clientDataJSON", 0, true, "{}"),
        "attestationObject" => new StringType("attestationObject", 0, true, "")
      ]);
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      if (!$currentUser->isLocalAccount()) {
        return $this->createError("Cannot add a 2FA token: Your account is managed by an external identity provider (SSO)");
      }

      $clientDataJSON = json_decode($this->getParam("clientDataJSON"), true);
      $attestationObjectRaw = base64_decode($this->getParam("attestationObject"));
      $twoFactorToken = $currentUser->getTwoFactorToken();
      $settings = $this->context->getSettings();
      $relyingParty = $settings->getSiteName();
      $sql = $this->context->getSQL();
      $domain = getCurrentHostName();

      if (!$clientDataJSON || !$attestationObjectRaw) {
        $challenge = null;
        if ($twoFactorToken) {
          if ($twoFactorToken->isConfirmed()) {
            return $this->createError("You already added a two factor token");
          } else if ($twoFactorToken instanceof KeyBasedTwoFactorToken) {
            $challenge = $twoFactorToken->getChallenge();
          } else {
            $twoFactorToken->delete($sql);
          }
        }

        if ($challenge === null) {
          $twoFactorToken = KeyBasedTwoFactorToken::create();
          $challenge = $twoFactorToken->getChallenge();
          $this->success = ($twoFactorToken->save($sql) !== false);
          $this->lastError = $sql->getLastError();
          if (!$this->success) {
            return false;
          }

          $currentUser->setTwoFactorToken($twoFactorToken);
          $this->success = $currentUser->save($sql, ["twoFactorToken"]) !== false;
          $this->lastError = $sql->getLastError();
          if (!$this->success) {
            return false;
          }
        }

        $this->result["data"] = [
          "challenge" => $challenge,
          "relyingParty" => [
            "name" => $relyingParty,
            "id" => $domain
          ],
        ];
      } else {
        if ($twoFactorToken === null) {
          return $this->createError("Request a registration first.");
        } else if (!($twoFactorToken instanceof KeyBasedTwoFactorToken)) {
          return $this->createError("You already got a 2FA token");
        }

        if (!$this->verifyClientDataJSON($clientDataJSON, $twoFactorToken)) {
          return false;
        }

        $attestationObject = new AttestationObject($attestationObjectRaw);
        $authData = $attestationObject->getAuthData();
        if (!$this->verifyAuthData($authData)) {
          return false;
        }

        $publicKey = $authData->getPublicKey();
        if ($publicKey->getUsedAlgorithm() !== -7) {
          return $this->createError("Unsupported key type. Expected: -7");
        }

        $twoFactorToken->authenticate();
        $this->success = $twoFactorToken->confirmKeyBased($sql, base64_encode($authData->getCredentialID()), $publicKey) !== false;
        $this->lastError = $sql->getLastError();

        if ($this->success) {
          $this->result["twoFactorToken"] = $twoFactorToken->jsonSerialize();
          $this->context->invalidateSessions(true);
        }
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to register a 2FA hardware-key";
    }
  }

  class VerifyKey extends TfaAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "credentialID" => new StringType("credentialID"),
        "clientDataJSON" => new StringType("clientDataJSON"),
        "authData" => new StringType("authData"),
        "signature" => new StringType("signature"),
      ]);
      $this->csrfTokenRequired = false;
      $this->rateLimiting = new RateLimiting(
        null,
        new RateLimitRule(20, 60, RateLimitRule::SECOND)
      );
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      if (!$currentUser) {
        return $this->createError("You are not logged in.");
      }

      $twoFactorToken = $currentUser->getTwoFactorToken();
      if (!$twoFactorToken) {
        return $this->createError("You did not add a two factor token yet.");
      } else if (!($twoFactorToken instanceof KeyBasedTwoFactorToken)) {
        return $this->createError("Invalid 2FA-token endpoint");
      } else if (!$twoFactorToken->isConfirmed()) {
        return $this->createError("2FA-Key not confirmed yet");
      }

      $credentialID = base64url_decode($this->getParam("credentialID"));
      if ($credentialID !== $twoFactorToken->getCredentialId()) {
        return $this->createError("credential ID does not match");
      }

      $jsonData = $this->getParam("clientDataJSON");
      if (!$this->verifyClientDataJSON(json_decode($jsonData, true), $twoFactorToken)) {
        return false;
      }

      $authDataRaw = base64_decode($this->getParam("authData"));
      $authData = new AuthenticationData($authDataRaw);
      if (!$this->verifyAuthData($authData)) {
        return false;
      }

      $clientDataHash = hash("sha256", $jsonData, true);
      $signature = base64_decode($this->getParam("signature"));

      $this->success = $twoFactorToken->verify($signature, $authDataRaw . $clientDataHash);
      if ($this->success) {
        $twoFactorToken->authenticate();
      } else {
        $this->lastError = "Verification failed";
      }

      return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to verify a 2FA hardware-key";
    }
  }
}