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
  private array $trustedDomains;
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
    $query = $sql->select("name", "value")->from("Settings");

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
        $settings[$row["name"]] = json_decode($row["value"], true);
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
      return (empty($res)) ? $defaultValue : json_decode($res[0]["value"], true);
    }
  }

  public function isInstalled(): bool {
    return $this->installationComplete;
  }

  public static function loadDefaults(): Settings {
    $protocol = getProtocol();
    $hostname = getCurrentHostName();
    $settings = new Settings();

    // General
    $settings->siteName = "WebBase";
    $settings->baseUrl = "$protocol://$hostname";
    $settings->trustedDomains = [$hostname];
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
      $this->allowedExtensions = $result["allowed_extensions"] ?? $this->allowedExtensions;
      $this->trustedDomains = $result["trusted_domains"] ?? $this->trustedDomains;
      date_default_timezone_set($this->timeZone);
    }

    return false;
  }

  public function addRows(Insert $query): void {
    $query->addRow("site_name", json_encode($this->siteName), false, false)
      ->addRow("base_url", json_encode($this->baseUrl), false, false)
      ->addRow("trusted_domains", json_encode($this->trustedDomains), false, false)
      ->addRow("user_registration_enabled", json_encode($this->registrationAllowed), false, false)
      ->addRow("installation_completed", json_encode($this->installationComplete), true, true)
      ->addRow("time_zone", json_encode($this->timeZone), false, false)
      ->addRow("recaptcha_enabled", json_encode($this->recaptchaEnabled), false, false)
      ->addRow("recaptcha_public_key", json_encode($this->recaptchaPublicKey), false, false)
      ->addRow("recaptcha_private_key", json_encode($this->recaptchaPrivateKey), true, false)
      ->addRow("allowed_extensions", json_encode($this->allowedExtensions), false, false)
      ->addRow("mail_host", '""', false, false)
      ->addRow("mail_port", '587', false, false)
      ->addRow("mail_username", '""', false, false)
      ->addRow("mail_password", '""', true, false)
      ->addRow("mail_from", '""', false, false)
      ->addRow("mail_last_sync", '""', false, false)
      ->addRow("mail_footer", '""', false, false)
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

  public function isTrustedDomain(string $domain): bool {
    $domain = strtolower($domain);
    foreach ($this->trustedDomains as $trustedDomain) {
      $trustedDomain = trim(strtolower($trustedDomain));
      if ($trustedDomain === $domain) {
        return true;
      }


      // *.def.com <-> abc.def.com
      if (startsWith($trustedDomain, "*.") && endsWith($domain, substr($trustedDomain, 1))) {
        return true;
      }
    }

    return false;
  }

  public function getTrustedDomains(): array {
    return $this->trustedDomains;
  }
}