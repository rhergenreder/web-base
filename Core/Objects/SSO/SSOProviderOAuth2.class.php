<?php

namespace Core\Objects\SSO;

use Core\Objects\Context;
use Core\Objects\DatabaseEntity\SsoProvider;
use Core\Objects\DatabaseEntity\User;

class SSOProviderOAuth2 extends SSOProvider {

  const TYPE = "oauth2";

  public function __construct(?int $id = null) {
    parent::__construct(self::TYPE, $id);
  }

  public function login(Context $context, ?string $redirectUrl) {
    // TODO: Implement login() method.
  }

  public function parseResponse(Context $context, string $response): ?User {
    // TODO: Implement parseResponse() method.
  }
}