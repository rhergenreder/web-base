<?php

namespace Core\Objects\Router;

use Core\Objects\DatabaseEntity\Route;
use JetBrains\PhpStorm\Pure;

class RedirectRoute extends Route {

  private int $code;

  public function __construct(string $type, string $pattern, bool $exact, string $destination, int $code = 307) {
    parent::__construct($type, $pattern, $destination, $exact);
    $this->code = $code;
  }

  #[Pure] private function getDestination(): string {
    return $this->getTarget();
  }

  public function call(Router $router, array $params): string {
    header("Location: " . $this->getDestination());
    http_response_code($this->code);
    return "";
  }

  protected function getArgs(): array {
    return array_merge(parent::getArgs(), [$this->getDestination(), $this->code]);
  }
}