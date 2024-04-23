<?php

namespace Core\Objects\Captcha;

class HCaptchaProvider extends CaptchaProvider {

  public function __construct(string $siteKey, string $secretKey) {
    parent::__construct($siteKey, $secretKey);
  }

  public function verify(string $captcha, string $action): bool {
    $success = true;
    $url = "https://api.hcaptcha.com/siteverify";
    $response = $this->performVerifyRequest($url, $captcha);
    $this->error = "Could not verify captcha: Invalid response from hCaptcha received.";

    if ($response) {
      $success = $response["success"];
      if (!$success) {
        $this->error = "Captcha verification failed.";
      }
    }

    return $success;
  }

  public function getName(): string {
    return CaptchaProvider::HCAPTCHA;
  }
}