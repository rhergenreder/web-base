<?php

namespace Core\API;

use Core\Objects\Context;

class Info extends Request {

  public function __construct(Context $context, bool $externalCall = false) {
    parent::__construct($context, $externalCall, []);
    $this->csrfTokenRequired = false;
  }

  protected function _execute(): bool {

    $settings = $this->context->getSettings();
    $this->result["info"] = [
      "registrationAllowed" => $settings->isRegistrationAllowed(),
      "recaptchaEnabled" => $settings->isRecaptchaEnabled(),
      "version" => WEBBASE_VERSION,
      "siteName" => $settings->getSiteName(),
    ];

    return true;
  }

  public static function getDescription(): string {
    return "Returns general information about the Site";
  }
}