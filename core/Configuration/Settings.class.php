<?php

/**
 * Do not change settings here, they are dynamically loaded from database.
 */

namespace Configuration;

use Driver\SQL\Query\Insert;
use Objects\User;

class Settings {

  private string $siteName;
  private string $baseUrl;
  private string $jwtSecret;
  private bool $installationComplete;
  private bool $registrationAllowed;
  private bool $recaptchaEnabled;
  private string $recaptchaPublicKey;
  private string $recaptchaPrivateKey;

  public function getJwtSecret(): string {
    return $this->jwtSecret;
  }

  public function isInstalled() {
    return $this->installationComplete;
  }

  public static function loadDefaults() : Settings {
    $hostname = $_SERVER["SERVER_NAME"];
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
    return $settings;
  }

  public function loadFromDatabase(User $user) {
    $req = new \Api\Settings\Get($user);
    $success = $req->execute();

    if ($success) {
      $result = $req->getResult()["settings"];
      $this->siteName = $result["site_name"] ?? $this->siteName;
      $this->registrationAllowed = $result["user_registration_enabled"] ?? $this->registrationAllowed;
      $this->installationComplete = $result["installation_completed"] ?? $this->installationComplete;
      $this->jwtSecret = $result["jwt_secret"] ?? $this->jwtSecret;
      $this->recaptchaEnabled = $result["recaptcha_enabled"] ?? $this->recaptchaEnabled;
      $this->recaptchaPublicKey = $result["recaptcha_public_key"] ?? $this->recaptchaPublicKey;
      $this->recaptchaPrivateKey = $result["recaptcha_private_key"] ?? $this->recaptchaPrivateKey;

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
      ->addRow("recaptcha_private_key", $this->recaptchaPrivateKey, true, false);
  }

  public function getSiteName() {
    return $this->siteName;
  }

  public function getBaseUrl() {
    return $this->baseUrl;
  }

  public function isRecaptchaEnabled() {
    return $this->recaptchaEnabled;
  }

  public function getRecaptchaSiteKey() {
    return $this->recaptchaPublicKey;
  }

  public function getRecaptchaSecretKey() {
    return $this->recaptchaPrivateKey;
  }
}