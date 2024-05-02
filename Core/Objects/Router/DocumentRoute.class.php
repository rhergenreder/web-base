<?php

namespace Core\Objects\Router;

use Core\Driver\SQL\SQL;
use Core\Elements\Document;
use Core\Objects\Context;
use Core\Objects\DatabaseEntity\Attribute\Transient;
use Core\Objects\DatabaseEntity\Route;
use Core\Objects\Search\Searchable;
use Core\Objects\Search\SearchQuery;
use JetBrains\PhpStorm\Pure;
use ReflectionException;

class DocumentRoute extends Route {

  use Searchable;

  #[Transient]
  private array $args;

  #[Transient]
  private ?\ReflectionClass $reflectionClass = null;

  public function __construct(string $pattern, bool $exact, string $className, ...$args) {
    parent::__construct("dynamic", $pattern, $className, $exact);
    $this->args = $args;
    $this->extra = json_encode($args);
  }

  protected function readExtra() {
    parent::readExtra();
    $this->args = json_decode($this->extra, true) ?? [];
  }

  public function preInsert(array &$row) {
    parent::preInsert($row);
    $this->extra = json_encode($this->args, JSON_UNESCAPED_SLASHES);
  }

  #[Pure] private function getClassName(): string {
    return $this->getTarget();
  }

  private function loadClass(): bool {

    if ($this->reflectionClass === null) {
      try {
        $file = getClassPath($this->getClassName());
        if (file_exists($file)) {
          $this->reflectionClass = new \ReflectionClass($this->getClassName());
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

  public function match(string $url): bool|array {
    $match = parent::match($url);
    if ($match === false || !$this->loadClass()) {
      return false;
    }

    return $match;
  }

  protected function getArgs(): array {
    return array_merge(parent::getArgs(), [$this->getClassName()], $this->args);
  }

  public function call(Router $router, array $params): string {
    $className = $this->getClassName();

    try {
      if (!$this->loadClass()) {
        $router->getLogger()->warning("Error loading class: $className");
        return $router->returnStatusCode(500, [ "message" =>  "Error loading class: $className"]);
      }

      $args = array_merge([$router], $this->args, $params);
      $document = $this->reflectionClass->newInstanceArgs($args);
      return $document->load($params);
    } catch (\ReflectionException $e) {
      $router->getLogger()->error("Error loading class: $className: " . $e->getMessage());
      return $router->returnStatusCode(500, [ "message" =>  "Error loading class $className: " . $e->getMessage()]);
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