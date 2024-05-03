<?php

namespace Core\API\Traits;

use Core\API\Parameter\StringType;
use Core\API\VerifyCaptcha;
use Core\Objects\Context;

trait Captcha {

  function addCaptchaParameters(array &$parameters): void {
    $settings = $this->context->getSettings();
    if ($settings->isCaptchaEnabled()) {
      $parameters["captcha"] = new StringType("captcha");
    }
  }

  function checkCaptcha(string $action): bool {
    $settings = $this->context->getSettings();
    if ($settings->isCaptchaEnabled()) {
      $captcha = $this->getParam("captcha");
      $req = new VerifyCaptcha($this->context);
      if (!$req->execute(array("captcha" => $captcha, "action" => $action))) {
        return $this->createError($req->getLastError());
      }
    }

    return true;
  }
}