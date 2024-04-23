<?php

namespace Core\API;

use Core\API\Parameter\StringType;
use Core\Objects\Context;

class VerifyCaptcha extends Request {

  public function __construct(Context $context, bool $externalCall = false) {
    parent::__construct($context, $externalCall, array(
      "captcha" => new StringType("captcha"),
      "action" => new StringType("action"),
    ));

    $this->isPublic = false;
  }

  public function _execute(): bool {
    $settings = $this->context->getSettings();
    $captchaProvider = $settings->getCaptchaProvider();
    if ($captchaProvider === null) {
      return $this->createError("No Captcha configured.");
    }

    $captcha = $this->getParam("captcha");
    $action = $this->getParam("action");
    $this->success = $captchaProvider->verify($captcha, $action);
    $this->lastError = $captchaProvider->getError();
    return $this->success;
  }

  public static function getDescription(): string {
    return "Verifies a captcha response. This API is for internal use only.";
  }
}