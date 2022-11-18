<?php

namespace Core\Objects\Router;

use Core\Elements\Document;
use Core\Objects\Context;
use Core\Objects\Search\Searchable;
use Core\Objects\Search\SearchQuery;
use ReflectionException;

class DocumentRoute extends AbstractRoute {

  use Searchable;

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
        throw $exception;
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
    try {
      if (!$this->loadClass()) {
        return $router->returnStatusCode(500, [ "message" =>  "Error loading class: $this->className"]);
      }

      $args = array_merge([$router], $this->args, $params);
      $document = $this->reflectionClass->newInstanceArgs($args);
      return $document->load($params);
    } catch (\ReflectionException $e) {
      return $router->returnStatusCode(500, [ "message" =>  "Error loading class $this->className: " . $e->getMessage()]);
    }
  }

  public function doSearch(Context $context, SearchQuery $query): array {
    try {
      if ($this->loadClass()) {
        $args = array_merge([$context->router], $this->args);
        $document = $this->reflectionClass->newInstanceArgs($args);
        if ($document->isSearchable()) {
          return $document->doSearch($query, $this);
        }
      }

      return [];
    } catch (\ReflectionException) {
      return [];
    }
  }
}