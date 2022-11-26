<?php

namespace Core\API {

  use Core\Objects\Context;
  use Core\Objects\TwoFactor\AuthenticationData;
  use Core\Objects\TwoFactor\KeyBasedTwoFactorToken;

  abstract class TfaAPI extends Request {

    private bool $userVerificationRequired;

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
      $this->loginRequired = true;
      $this->apiKeyAllowed = false;
      $this->userVerificationRequired = false;
    }

    protected function verifyAuthData(AuthenticationData $authData): bool {
      $settings = $this->context->getSettings();
      // $relyingParty = $settings->getSiteName();
      $domain = parse_url($settings->getBaseUrl(),  PHP_URL_HOST);
      // $domain = "localhost";

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

    protected function verifyClientDataJSON($jsonData, KeyBasedTwoFactorToken $token): bool {
      $settings = $this->context->getSettings();
      $expectedType = $token->isConfirmed() ? "webauthn.get" : "webauthn.create";
      $type = $jsonData["type"] ?? "null";
      if ($type !== $expectedType) {
        return $this->createError("Invalid client data json type. Expected: '$expectedType', Got: '$type'");
      } else if ($token->getData() !== base64url_decode($jsonData["challenge"] ?? "")) {
        return $this->createError("Challenge does not match");
      } else if (($jsonData["origin"] ?? null) !== $settings->getBaseURL()) {
        $baseUrl = $settings->getBaseURL();
        return $this->createError("Origin does not match. Expected: '$baseUrl', Got: '${$jsonData["origin"]}'");
      }

      return true;
    }
  }
}

namespace Core\API\TFA {

  use Core\API\Parameter\StringType;
  use Core\API\TfaAPI;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Objects\Context;
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
        $res = $sql->select("password")
          ->from("User")
          ->whereEq("id", $currentUser->getId())
          ->execute();
        $this->success = !empty($res);
        $this->lastError = $sql->getLastError();
        if (!$this->success) {
          return false;
        } else if (!password_verify($password, $res[0]["password"])) {
          return $this->createError("Wrong password");
        }
      } else if ($token->isConfirmed()) {
        // if the token is fully confirmed, require a password to remove it
        return $this->createError("Missing parameter: password");
      }

      $res = $sql->delete("2FA")
        ->whereEq("id", $token->getId())
        ->execute();

      $this->success = $res !== false;
      $this->lastError = $sql->getLastError();

      if ($this->success && $token->isConfirmed()) {
        // send an email
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
            "to" => $currentUser->getEmail(),
            "subject" => "[$siteName] 2FA-Authentication removed",
            "body" => $body,
            "gpgFingerprint" => $gpg?->getFingerprint()
          ]);
        }

        $this->lastError = $req->getLastError();
      }

      return $this->success;
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
      } else if (!($twoFactorToken instanceof TimeBasedTwoFactorToken)) {
        $twoFactorToken = new TimeBasedTwoFactorToken(generateRandomString(32, "base32"));
        $sql = $this->context->getSQL();
        $this->success = $sql->insert("2FA", ["type", "data"])
            ->addRow("totp", $twoFactorToken->getData())
            ->returning("id")
            ->execute() !== false;
        $this->lastError = $sql->getLastError();
        if ($this->success) {
          $this->success = $sql->update("User")
              ->set("2fa_id", $sql->getLastInsertId())
              ->whereEq("id", $currentUser->getId())
              ->execute() !== false;
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

      $sql = $this->context->getSQL();
      $this->success = $sql->update("2FA")
        ->set("confirmed", true)
        ->whereEq("id", $twoFactorToken->getId())
        ->execute() !== false;
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }

  class VerifyTotp extends TfaAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "code" => new StringType("code", 6)
      ]);
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      if (!$currentUser) {
        return $this->createError("You are not logged in.");
      }

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
  }

  // Key
  class RegisterKey extends TfaAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "clientDataJSON" => new StringType("clientDataJSON", 0, true, "{}"),
        "attestationObject" => new StringType("attestationObject", 0, true, "")
      ]);
      $this->loginRequired = true;
    }

    public function _execute(): bool {

      $currentUser = $this->context->getUser();
      $clientDataJSON = json_decode($this->getParam("clientDataJSON"), true);
      $attestationObjectRaw = base64_decode($this->getParam("attestationObject"));
      $twoFactorToken = $currentUser->getTwoFactorToken();
      $settings = $this->context->getSettings();
      $relyingParty = $settings->getSiteName();
      $sql = $this->context->getSQL();

      // TODO: for react development, localhost / HTTP_HOST is required, otherwise a DOMException is thrown
      $domain = parse_url($settings->getBaseUrl(),  PHP_URL_HOST);
      // $domain = "localhost";

      if (!$clientDataJSON || !$attestationObjectRaw) {
        if ($twoFactorToken) {
          if (!($twoFactorToken instanceof KeyBasedTwoFactorToken) || $twoFactorToken->isConfirmed()) {
            return $this->createError("You already added a two factor token");
          } else {
            $challenge = base64_encode($twoFactorToken->getData());
          }
        } else {
          $challenge = base64_encode(generateRandomString(32, "raw"));
          $res = $sql->insert("2FA", ["type", "data"])
            ->addRow("fido", $challenge)
            ->returning("id")
            ->execute();
          $this->success = ($res !== false);
          $this->lastError = $sql->getLastError();
          if (!$this->success) {
            return false;
          }

          $this->success = $sql->update("User")
            ->set("2fa_id", $sql->getLastInsertId())
            ->whereEq("id", $currentUser->getId())
            ->execute() !== false;
          $this->lastError = $sql->getLastError();
          if (!$this->success) {
            return false;
          }
        }

        $this->result["data"] = [
          "challenge" => $challenge,
          "id" => $currentUser->getId() . "@" . $domain, // <userId>@<domain>
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

        $data = [
          "credentialID" => base64_encode($authData->getCredentialID()),
          "publicKey" => $publicKey->jsonSerialize()
        ];

        $this->success = $sql->update("2FA")
            ->set("data", json_encode($data))
            ->set("confirmed", true)
            ->whereEq("id", $twoFactorToken->getId())
            ->execute() !== false;
        $this->lastError = $sql->getLastError();
      }

      return $this->success;
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
      $this->loginRequired = true;
      $this->csrfTokenRequired = false;
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
  }
}