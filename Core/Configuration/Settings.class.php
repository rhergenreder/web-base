<?php

/**
 * Do not change settings here, they are dynamically loaded from database.
 */

namespace Core\Configuration;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Column\Column;
use Core\Driver\SQL\Condition\CondNot;
use Core\Driver\SQL\Condition\CondRegex;
use Core\Driver\SQL\Query\Insert;
use Core\Driver\SQL\SQL;
use Core\Objects\Context;

class Settings {

  //
  private bool $installationComplete;

  // general settings
  private string $siteName;
  private string $baseUrl;
  private bool $registrationAllowed;
  private array $allowedExtensions;
  private string $timeZone;

  // recaptcha
  private bool $recaptchaEnabled;
  private string $recaptchaPublicKey;
  private string $recaptchaPrivateKey;

  // mail
  private bool $mailEnabled;
  private string $mailSender;
  private string $mailFooter;
  private bool $mailAsync;

  //
  private Logger $logger;

  public function __construct() {
    $this->logger = new Logger("Settings");
  }

  public static function getAll(?SQL $sql, ?string $pattern = null, bool $external = false): ?array {
    $query = $sql->select("name", "value") ->from("Settings");

    if ($pattern) {
      $query->where(new CondRegex(new Column("name"), $pattern));
    }

    if ($external) {
      $query->where(new CondNot("private"));
    }

    $res = $query->execute();
    if ($res !== false && $res !== null) {
      $settings = array();
      foreach($res as $row) {
        $settings[$row["name"]] = $row["value"];
      }
      return $settings;
    } else {
      return null;
    }
  }

  public static function get(?SQL $sql, string $key, mixed $defaultValue): mixed {
    $res = $sql->select("value") ->from("Settings")
      ->whereEq("name", $key)
      ->execute();

    if ($res === false || $res === null) {
      return null;
    } else {
      return (empty($res)) ? $defaultValue : $res[0]["value"];
    }
  }

  public function isInstalled(): bool {
    return $this->installationComplete;
  }

  public static function loadDefaults(): Settings {
    $hostname = $_SERVER["SERVER_NAME"] ?? null;
    if (empty($hostname)) {
      $hostname = $_SERVER["HTTP_HOST"] ?? null;
      if (empty($hostname)) {
        $hostname = "localhost";
      }
    }

    $protocol = getProtocol();
    $settings = new Settings();

    // General
    $settings->siteName = "WebBase";
    $settings->baseUrl = "$protocol://$hostname";
    $settings->allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'htm', 'html'];
    $settings->installationComplete = false;
    $settings->registrationAllowed = false;
    $settings->timeZone = date_default_timezone_get();

    // Recaptcha
    $settings->recaptchaEnabled = false;
    $settings->recaptchaPublicKey = "";
    $settings->recaptchaPrivateKey = "";

    // Mail
    $settings->mailEnabled = false;
    $settings->mailSender = "webmaster@localhost";
    $settings->mailFooter = "";
    $settings->mailAsync = false;

    return $settings;
  }

  public function loadFromDatabase(Context $context): bool {
    $this->logger = new Logger("Settings", $context->getSQL());
    $req = new \Core\API\Settings\Get($context);
    $success = $req->execute();

    if ($success) {
      $result = $req->getResult()["settings"];
      $this->siteName = $result["site_name"] ?? $this->siteName;
      $this->baseUrl = $result["base_url"] ?? $this->baseUrl;
      $this->registrationAllowed = $result["user_registration_enabled"] ?? $this->registrationAllowed;
      $this->installationComplete = $result["installation_completed"] ?? $this->installationComplete;
      $this->timeZone = $result["time_zone"] ?? $this->timeZone;
      $this->recaptchaEnabled = $result["recaptcha_enabled"] ?? $this->recaptchaEnabled;
      $this->recaptchaPublicKey = $result["recaptcha_public_key"] ?? $this->recaptchaPublicKey;
      $this->recaptchaPrivateKey = $result["recaptcha_private_key"] ?? $this->recaptchaPrivateKey;
      $this->mailEnabled = $result["mail_enabled"] ?? $this->mailEnabled;
      $this->mailSender = $result["mail_from"] ?? $this->mailSender;
      $this->mailFooter = $result["mail_footer"] ?? $this->mailFooter;
      $this->mailAsync = $result["mail_async"] ?? $this->mailAsync;
      $this->allowedExtensions = explode(",", $result["allowed_extensions"] ?? strtolower(implode(",", $this->allowedExtensions)));
      date_default_timezone_set($this->timeZone);
    }

    return false;
  }

  public function addRows(Insert $query): void {
    $query->addRow("site_name", $this->siteName, false, false)
      ->addRow("base_url", $this->baseUrl, false, false)
      ->addRow("user_registration_enabled", $this->registrationAllowed ? "1" : "0", false, false)
      ->addRow("installation_completed", $this->installationComplete ? "1" : "0", true, true)
      ->addRow("time_zone", $this->timeZone, false, false)
      ->addRow("recaptcha_enabled", $this->recaptchaEnabled ? "1" : "0", false, false)
      ->addRow("recaptcha_public_key", $this->recaptchaPublicKey, false, false)
      ->addRow("recaptcha_private_key", $this->recaptchaPrivateKey, true, false)
      ->addRow("allowed_extensions", implode(",", $this->allowedExtensions), true, false)
      ->addRow("mail_host", "", false, false)
      ->addRow("mail_port", "", false, false)
      ->addRow("mail_username", "", false, false)
      ->addRow("mail_password", "", true, false)
      ->addRow("mail_from", "", false, false)
      ->addRow("mail_last_sync", "", false, false)
      ->addRow("mail_footer", "", false, false)
      ->addRow("mail_async", false, false, false);
  }

  public function getSiteName(): string {
    return $this->siteName;
  }

  public function getTimeZone(): string {
    return $this->timeZone;
  }

  public function setTimeZone(string $tz) {
    $this->timeZone = $tz;
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

  public function isMailAsync(): bool {
    return $this->mailAsync;
  }

  public function getMailSender(): string {
    return $this->mailSender;
  }

  public function isExtensionAllowed(string $ext): bool {
    return empty($this->allowedExtensions) || in_array(strtolower(trim($ext)), $this->allowedExtensions);
  }

  public function getDomain(): string {
    return parse_url($this->getBaseUrl(), PHP_URL_HOST);
  }

  public function getLogger(): Logger {
    return $this->logger;
  }
}