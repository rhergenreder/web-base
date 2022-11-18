<?php

namespace Core\Objects\Router;

class RedirectRoute extends AbstractRoute {

  private string $destination;
  private int $code;

  public function __construct(string $pattern, bool $exact, string $destination, int $code = 307) {
    parent::__construct($pattern, $exact);
    $this->destination = $destination;
    $this->code = $code;
  }

  public function call(Router $router, array $params): string {
    header("Location: $this->destination");
    http_response_code($this->code);
    return "";
  }

  protected function getArgs(): array {
    return array_merge(parent::getArgs(), [$this->destination, $this->code]);
  }
}