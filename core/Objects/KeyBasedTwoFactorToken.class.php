<?php

namespace Objects;

class KeyBasedTwoFactorToken extends TwoFactorToken {

  const TYPE = "fido2";

  public function __construct(string $secret, ?int $id = null, bool $confirmed = false) {
    parent::__construct(self::TYPE, $secret, $id, $confirmed);
  }

}