<?php

/**
 * Do not change settings here, they are dynamically loaded from database.
 */

namespace Core\Configuration;

use Core\Driver\Logger\Logger;
use Core\Driver\SQL\Query\Insert;
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

  // jwt
  private ?string $jwtPublicKey;
  private ?string $jwtSecretKey;
  private string $jwtAlgorithm;

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

  public function getJwtPublicKey(bool $allowPrivate = true): ?\Firebase\JWT\Key {
    if (empty($this->jwtPublicKey)) {
      // we might have a symmetric key, should we instead return the private key?
      return $allowPrivate ? new \Firebase\JWT\Key($this->jwtSecretKey, $this->jwtAlgorithm) : null;
    } else {
      return new \Firebase\JWT\Key($this->jwtPublicKey, $this->jwtAlgorithm);
    }
  }

  public function getJwtSecretKey(): \Firebase\JWT\Key {
    return new \Firebase\JWT\Key($this->jwtSecretKey, $this->jwtAlgorithm);
  }

  public function isInstalled(): bool {
    return $this->installationComplete;
  }

  public static function loadDefaults(): Settings {
    $hostname = $_SERVER["SERVER_NAME"];
    if (empty($hostname)) {
      $hostname = "localhost";
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

    // JWT
    $settings->jwtSecretKey = null;
    $settings->jwtPublicKey = null;
    $settings->jwtAlgorithm = "HS256";

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

  public function generateJwtKey(string $algorithm = null): bool {
    $this->jwtAlgorithm = $algorithm ?? $this->jwtAlgorithm;

    // TODO: key encryption necessary?
    if (in_array($this->jwtAlgorithm, ["HS256", "HS384", "HS512"])) {
      $this->jwtSecretKey = generateRandomString(32);
      $this->jwtPublicKey = null;
    } else if (in_array($this->jwtAlgorithm, ["RS256", "RS384", "RS512"])) {
      $bits = intval(substr($this->jwtAlgorithm, 2));
      $private_key = openssl_pkey_new(["private_key_bits" => $bits]);
      $this->jwtPublicKey = openssl_pkey_get_details($private_key)['key'];
      openssl_pkey_export($private_key, $this->jwtSecretKey);
    } else if (in_array($this->jwtAlgorithm, ["ES256", "ES384"])) {
      // $ec = new \Elliptic\EC('secp256k1'); ??
      $this->logger->error("JWT algorithm: '$this->jwtAlgorithm' is currently not supported.");
      return false;
    } else if ($this->jwtAlgorithm == "EdDSA") {
      $keyPair = sodium_crypto_sign_keypair();
      $this->jwtSecretKey = base64_encode(sodium_crypto_sign_secretkey($keyPair));
      $this->jwtPublicKey = base64_encode(sodium_crypto_sign_publickey($keyPair));
    } else {
      $this->logger->error("Invalid JWT algorithm: '$this->jwtAlgorithm', expected one of: " .
        implode(",", array_keys(\Firebase\JWT\JWT::$supported_algs)));
      return false;
    }

    return true;
  }

  public static function isJwtAlgorithmSupported(string $algorithm): bool {
    return in_array(strtoupper($algorithm), ["HS256", "HS384", "HS512", "RS256", "RS384", "RS512", "EDDSA"]);
  }

  public function saveJwtKey(Context $context): \Core\API\Settings\Set {
    $req = new \Core\API\Settings\Set($context);
    $req->execute(array("settings" => array(
      "jwt_secret_key" => $this->jwtSecretKey,
      "jwt_public_key" => $this->jwtSecretKey,
      "jwt_algorithm" => $this->jwtAlgorithm,
    )));

    return $req;
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
      $this->jwtSecretKey = $result["jwt_secret_key"] ?? $this->jwtSecretKey;
      $this->jwtPublicKey = $result["jwt_public_key"] ?? $this->jwtPublicKey;
      $this->jwtAlgorithm = $result["jwt_algorithm"] ?? $this->jwtAlgorithm;
      $this->recaptchaEnabled = $result["recaptcha_enabled"] ?? $this->recaptchaEnabled;
      $this->recaptchaPublicKey = $result["recaptcha_public_key"] ?? $this->recaptchaPublicKey;
      $this->recaptchaPrivateKey = $result["recaptcha_private_key"] ?? $this->recaptchaPrivateKey;
      $this->mailEnabled = $result["mail_enabled"] ?? $this->mailEnabled;
      $this->mailSender = $result["mail_from"] ?? $this->mailSender;
      $this->mailFooter = $result["mail_footer"] ?? $this->mailFooter;
      $this->mailAsync = $result["mail_async"] ?? $this->mailAsync;
      $this->allowedExtensions = explode(",", $result["allowed_extensions"] ?? strtolower(implode(",", $this->allowedExtensions)));

      if (!isset($result["jwt_secret_key"])) {
        if ($this->generateJwtKey()) {
          $this->saveJwtKey($context);
        }
      }

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
      ->addRow("jwt_secret_key", $this->jwtSecretKey, true, false)
      ->addRow("jwt_public_key", $this->jwtPublicKey, false, false)
      ->addRow("jwt_algorithm", $this->jwtAlgorithm, false, false)
      ->addRow("recaptcha_enabled", $this->recaptchaEnabled ? "1" : "0", false, false)
      ->addRow("recaptcha_public_key", $this->recaptchaPublicKey, false, false)
      ->addRow("recaptcha_private_key", $this->recaptchaPrivateKey, true, false)
      ->addRow("allowed_extensions", implode(",", $this->allowedExtensions), true, false);
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