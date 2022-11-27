<?php

namespace Core\Objects\Router;

class RedirectTemporaryRoute extends RedirectRoute {
  public function __construct(string $pattern, bool $exact, string $destination) {
    parent::__construct("redirect_temporary", $pattern, $exact, $destination, 307);
  }
}