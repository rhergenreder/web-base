<?php

namespace Core\Objects\Router;

class RedirectPermanentlyRoute extends RedirectRoute {
  public function __construct(string $pattern, bool $exact, string $destination) {
    parent::__construct("redirect_permanently", $pattern, $exact, $destination, 308);
  }
}