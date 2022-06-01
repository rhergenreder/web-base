<?php

namespace Objects\Router;

use Elements\Document;
use ReflectionException;

class DocumentRoute extends AbstractRoute {

  private string $className;
  private array $args;
  private ?\ReflectionClass $reflectionClass;

  public function __construct(string $pattern, bool $exact, string $className, ...$args) {
    parent::__construct($pattern, $exact);
    $this->className = $className;
    $this->args = $args;
    $this->reflectionClass = null;
  }

  private function loadClass(): bool {

    if ($this->reflectionClass === null) {
      try {
        $file = getClassPath($this->className);
        if (file_exists($file)) {
          $this->reflectionClass = new \ReflectionClass($this->className);
          if ($this->reflectionClass->isSubclassOf(Document::class)) {
            return true;
          }
        }
      } catch (ReflectionException $exception) {
        $this->reflectionClass = null;
        return false;
      }

      $this->reflectionClass = null;
      return false;
    }

    return true;
  }

  public function match(string $url) {
    $match = parent::match($url);
    if ($match === false || !$this->loadClass()) {
      return false;
    }

    return $match;
  }

  protected function getArgs(): array {
    return array_merge(parent::getArgs(), [$this->className], $this->args);
  }

  public function call(Router $router, array $params): string {
    if (!$this->loadClass()) {
      return $router->returnStatusCode(500, [ "message" =>  "Error loading class: $this->className"]);
    }

    try {
      $args = array_merge([$router->getUser()], $this->args);
      $document = $this->reflectionClass->newInstanceArgs($args);
      return $document->getCode($params);
    } catch (\ReflectionException $e) {
      return $router->returnStatusCode(500, [ "message" =>  "Error loading class $this->className: " . $e->getMessage()]);
    }
  }
}