<?php

namespace Api;

use Api\Parameter\StringType;
use Objects\User;

class VerifyCaptcha extends Request {

  public function __construct(User $user, bool $externalCall = false) {
    parent::__construct($user, $externalCall, array(
      "captcha" => new StringType("captcha"),
      "action" => new StringType("action"),
    ));

    $this->isPublic = false;
  }

  public function _execute(): bool {
    $settings = $this->user->getConfiguration()->getSettings();
    if (!$settings->isRecaptchaEnabled()) {
      return $this->createError("Google reCaptcha is not enabled.");
    }

    $url = "https://www.google.com/recaptcha/api/siteverify";
    $secret = $settings->getRecaptchaSecretKey();
    $captcha = $this->getParam("captcha");
    $action = $this->getParam("action");

    $params = array(
      "secret" => $secret,
      "response" => $captcha
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = @json_decode(curl_exec($ch), true);
    curl_close($ch);

    $this->success = false;
    $this->lastError = "Could not verify captcha: No response from google received.";

    if ($response) {
      $this->success = $response["success"];
      if (!$this->success) {
        $this->lastError = "Could not verify captcha: " . implode(";", $response["error-codes"]);
      } else {
        $score = $response["score"];
        if ($action !== $response["action"]) {
          $this->createError("Could not verify captcha: Action does not match");
        } else if ($score < 0.7) {
          $this->createError("Could not verify captcha: Google ReCaptcha Score < 0.7 (Your score: $score), you are likely a bot");
        }
      }
    }

    return $this->success;
  }
}