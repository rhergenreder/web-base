<?php

namespace Core\Objects\Router;

use Core\Objects\DatabaseEntity\Route;

class EmptyRoute extends Route {

  public function __construct(string $pattern, bool $exact = true) {
    parent::__construct("empty", $pattern, $exact);
  }

  public function call(Router $router, array $params): string {
    return "";
  }
}