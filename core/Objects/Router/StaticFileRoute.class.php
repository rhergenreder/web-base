<?php

namespace Objects\Router;

class StaticFileRoute extends AbstractRoute {

  private string $path;
  private int $code;

  public function __construct(string $pattern, bool $exact, string $path, int $code = 200) {
    parent::__construct($pattern, $exact);
    $this->path = $path;
    $this->code = $code;
  }

  public function call(Router $router, array $params): string {
    http_response_code($this->code);
    return serveStatic(WEBROOT, $this->path);
  }

  protected function getArgs(): array {
    return array_merge(parent::getArgs(), [$this->path, $this->code]);
  }
}