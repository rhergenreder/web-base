<?php

namespace Core\Objects\SSO;

use Core\Objects\Context;
use Core\Objects\DatabaseEntity\SsoProvider;
use Core\Objects\DatabaseEntity\User;

class SSOProviderOIDC extends SSOProvider {

  const TYPE = "oidc";

  public function __construct(?int $id = null) {
    parent::__construct(self::TYPE, $id);
  }

  public function login(Context $context, ?string $redirectUrl) {

  }

  public function parseResponse(Context $context, string $response): ?User {
    // TODO: Implement parseResponse() method.
  }
}