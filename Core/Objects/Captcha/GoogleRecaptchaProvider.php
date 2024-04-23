<?php

namespace Core\Objects\Captcha;

class GoogleRecaptchaProvider extends CaptchaProvider {

  public function __construct(string $siteKey, string $secretKey) {
    parent::__construct($siteKey, $secretKey);
  }

  public function verify(string $captcha, string $action): bool {
    $url = "https://www.google.com/recaptcha/api/siteverify";

    $success = false;
    $response = $this->performVerifyRequest($url, $captcha);
    $this->error = "Could not verify captcha: Invalid response from Google received.";

    if ($response) {
      $success = $response["success"];
      if (!$success) {
        $this->error = "Could not verify captcha: " . implode(";", $response["error-codes"]);
      } else {
        $score = $response["score"];
        if ($action !== $response["action"]) {
          $success = false;
          $this->error = "Could not verify captcha: Action does not match";
        } else if ($score < 0.7) {
          $success = false;
          $this->error = "Could not verify captcha: Google ReCaptcha Score < 0.7 (Your score: $score), you are likely a bot";
        } else {
          $success = true;
          $this->error = "";
        }
      }
    }

    return $success;
  }

  public function getName(): string {
    return CaptchaProvider::RECAPTCHA;
  }
}