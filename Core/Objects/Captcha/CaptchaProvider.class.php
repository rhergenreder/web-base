<?php

namespace Core\Objects\Captcha;

use Core\Objects\ApiObject;

abstract class CaptchaProvider extends ApiObject {

  const NONE = "none";
  const RECAPTCHA = "recaptcha";
  const HCAPTCHA = "hcaptcha";

  const PROVIDERS = [self::NONE, self::RECAPTCHA, self::HCAPTCHA];

  private string $siteKey;
  private string $secretKey;
  protected string $error;

  public function __construct(string $siteKey, string $secretKey) {
    $this->siteKey = $siteKey;
    $this->secretKey = $secretKey;
    $this->error = "";
  }

  public function getSiteKey(): string {
    return $this->siteKey;
  }

  public function getError(): string {
    return $this->error;
  }

  public static function isValid(string $type): bool {
    return in_array($type, [self::RECAPTCHA, self::HCAPTCHA]);
  }

  public abstract function verify(string $captcha, string $action): bool;

  public abstract function getName(): string;

  public function jsonSerialize(): array {
    return [
      "name" => $this->getName(),
      "siteKey" => $this->getSiteKey(),
    ];
  }

  protected function performVerifyRequest(string $url, string $captcha) {
    $params = [
      "secret" => $this->secretKey,
      "response" => $captcha
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = @json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $response;
  }
}