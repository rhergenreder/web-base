<?php

/**
 * Do not change settings here, they are dynamically loaded from database.
 */

namespace Configuration;

use Driver\SQL\Query\Insert;
use Objects\User;

class Settings {

  //
  private bool $installationComplete;

  // settings
  private string $siteName;
  private string $baseUrl;
  private string $jwtSecret;
  private bool $registrationAllowed;
  private bool $recaptchaEnabled;
  private bool $mailEnabled;
  private string $recaptchaPublicKey;
  private string $recaptchaPrivateKey;
  private string $mailSender;
  private string $mailFooter;
  private array $allowedExtensions;

  public function getJwtKey(): \Firebase\JWT\Key {
    // TODO: allow the use of other JWT algorithms (e.g. RS256)
    return new \Firebase\JWT\Key($this->jwtSecret, "HS256");
  }

  public function isInstalled(): bool {
    return $this->installationComplete;
  }

  public static function loadDefaults(): Settings {
    $hostname = $_SERVER["SERVER_NAME"] ?? "localhost";
    $protocol = getProtocol();
    $jwt = generateRandomString(32);

    $settings = new Settings();
    $settings->siteName = "WebBase";
    $settings->baseUrl = "$protocol://$hostname";
    $settings->jwtSecret = $jwt;
    $settings->installationComplete = false;
    $settings->registrationAllowed = false;
    $settings->recaptchaPublicKey = "";
    $settings->recaptchaPrivateKey = "";
    $settings->recaptchaEnabled = false;
    $settings->mailEnabled = false;
    $settings->mailSender = "webmaster@localhost";
    $settings->mailFooter = "";
    $settings->allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'htm', 'html'];

    return $settings;
  }

  public function loadFromDatabase(User $user): bool {
    $req = new \Api\Settings\Get($user);
    $success = $req->execute();

    if ($success) {
      $result = $req->getResult()["settings"];
      $this->siteName = $result["site_name"] ?? $this->siteName;
      $this->baseUrl = $result["base_url"] ?? $this->baseUrl;
      $this->registrationAllowed = $result["user_registration_enabled"] ?? $this->registrationAllowed;
      $this->installationComplete = $result["installation_completed"] ?? $this->installationComplete;
      $this->jwtSecret = $result["jwt_secret"] ?? $this->jwtSecret;
      $this->recaptchaEnabled = $result["recaptcha_enabled"] ?? $this->recaptchaEnabled;
      $this->recaptchaPublicKey = $result["recaptcha_public_key"] ?? $this->recaptchaPublicKey;
      $this->recaptchaPrivateKey = $result["recaptcha_private_key"] ?? $this->recaptchaPrivateKey;
      $this->mailEnabled = $result["mail_enabled"] ?? $this->mailEnabled;
      $this->mailSender = $result["mail_from"] ?? $this->mailSender;
      $this->mailFooter = $result["mail_footer"] ?? $this->mailFooter;
      $this->allowedExtensions = explode(",", $result["allowed_extensions"] ?? strtolower(implode(",", $this->allowedExtensions)));

      if (!isset($result["jwt_secret"])) {
        $req = new \Api\Settings\Set($user);
        $req->execute(array("settings" => array(
          "jwt_secret" => $this->jwtSecret
        )));
      }
    }

    return false;
  }

  public function addRows(Insert $query) {
    $query->addRow("site_name", $this->siteName, false, false)
      ->addRow("base_url", $this->baseUrl, false, false)
      ->addRow("user_registration_enabled", $this->registrationAllowed ? "1" : "0", false, false)
      ->addRow("installation_completed", $this->installationComplete ? "1" : "0", true, true)
      ->addRow("jwt_secret", $this->jwtSecret, true, true)
      ->addRow("recaptcha_enabled", $this->recaptchaEnabled ? "1" : "0", false, false)
      ->addRow("recaptcha_public_key", $this->recaptchaPublicKey, false, false)
      ->addRow("recaptcha_private_key", $this->recaptchaPrivateKey, true, false)
      ->addRow("allowed_extensions", implode(",", $this->allowedExtensions), true, false);
  }

  public function getSiteName(): string {
    return $this->siteName;
  }

  public function getBaseUrl(): string {
    return $this->baseUrl;
  }

  public function isRecaptchaEnabled(): bool {
    return $this->recaptchaEnabled;
  }

  public function getRecaptchaSiteKey(): string {
    return $this->recaptchaPublicKey;
  }

  public function getRecaptchaSecretKey(): string {
    return $this->recaptchaPrivateKey;
  }

  public function isRegistrationAllowed(): bool {
    return $this->registrationAllowed;
  }

  public function isMailEnabled(): bool {
    return $this->mailEnabled;
  }

  public function getMailSender(): string {
    return $this->mailSender;
  }

  public function isExtensionAllowed(string $ext): bool {
    return empty($this->allowedExtensions) || in_array(strtolower(trim($ext)), $this->allowedExtensions);
  }
}