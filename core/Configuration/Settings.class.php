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

  public function getJwtSecret(): string {
    return $this->jwtSecret;
  }

  public function isInstalled() {
    return $this->installationComplete;
  }

  public static function loadDefaults() : Settings {
    $hostname = php_uname("n");
    $protocol = getProtocol();
    $jwt = generateRandomString(32);

    $settings = new Settings();
    $settings->siteName = "WebBase";
    $settings->baseUrl = "$protocol://$hostname";
    $settings->jwtSecret = $jwt;
    $settings->installationComplete = false;
    $settings->registrationAllowed = false;
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
    $query->addRow("site_name", $this->siteName, false)
      ->addRow("base_url", $this->baseUrl, false)
      ->addRow("user_registration_enabled", $this->registrationAllowed ? "1" : "0", false)
      ->addRow("installation_completed", $this->installationComplete ? "1" : "0", true)
      ->addRow("jwt_secret", $this->jwtSecret, true);
  }
}