<?php

namespace Core\Objects\Router;

use Core\Objects\DatabaseEntity\Attribute\Transient;
use Core\Objects\DatabaseEntity\Route;

class StaticRoute extends Route {

  #[Transient]
  private string $data;

  #[Transient]
  private int $code;

  public function __construct(string $pattern, bool $exact, string $data, int $code = 200) {
    parent::__construct("static", $pattern, "", $exact);
    $this->data = $data;
    $this->code = $code;
  }

  public function call(Router $router, array $params): string {
    http_response_code($this->code);
    return $this->data;
  }

  protected function getArgs(): array {
    return array_merge(parent::getArgs(), [$this->data, $this->code]);
  }
}