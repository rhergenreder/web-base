<?php

namespace Core\Objects\Router;

use Core\Objects\DatabaseEntity\Attribute\Transient;
use Core\Objects\DatabaseEntity\Route;
use JetBrains\PhpStorm\Pure;

class RedirectRoute extends Route {

  #[Transient]
  protected int $code;

  public function __construct(string $type, string $pattern, bool $exact, string $destination, int $code = 307) {
    parent::__construct($type, $pattern, $destination, $exact);
    $this->code = $code;
  }

  #[Pure] private function getDestination(): string {
    return $this->getTarget();
  }

  public function call(Router $router, array $params): string {
    $router->redirect($this->code, $this->getDestination());
    return "";
  }

  protected function getArgs(): array {
    return array_merge(parent::getArgs(), [$this->getDestination(), $this->code]);
  }
}