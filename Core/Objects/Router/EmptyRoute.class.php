<?php

namespace Core\Objects\Router;

class EmptyRoute extends AbstractRoute {

  public function __construct(string $pattern, bool $exact = true) {
    parent::__construct($pattern, $exact);
  }

  public function call(Router $router, array $params): string {
    return "";
  }
}