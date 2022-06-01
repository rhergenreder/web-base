<?php

namespace Objects\Router;

use Api\Parameter\Parameter;

abstract class AbstractRoute {

  private string $pattern;
  private bool $exact;

  public function __construct(string $pattern, bool $exact = true) {
    $this->pattern = $pattern;
    $this->exact = $exact;
  }

  private static function parseParamType(?string $type): ?int {
    if ($type === null || trim($type) === "") {
      return null;
    }

    $type = strtolower(trim($type));
    if (in_array($type, ["int", "integer"])) {
      return Parameter::TYPE_INT;
    } else if (in_array($type, ["float", "double"])) {
      return Parameter::TYPE_FLOAT;
    } else if (in_array($type, ["bool", "boolean"])) {
      return Parameter::TYPE_BOOLEAN;
    } else {
      return Parameter::TYPE_STRING;
    }
  }

  public function getPattern(): string {
    return $this->pattern;
  }

  public abstract function call(Router $router, array $params): string;

  protected function getArgs(): array {
    return [$this->pattern, $this->exact];
  }

  public function generateCache(): string {
    $reflection = new \ReflectionClass($this);
    $className = $reflection->getName();
    $args = implode(", ", array_map(function ($arg) {
      return var_export($arg, true);
    }, $this->getArgs()));
    return "new \\$className($args)";
  }

  public function match(string $url) {

    # /test/{abc}/{param:?}/{xyz:int}/{aaa:int?}
    $patternParts = explode("/", Router::cleanURL($this->pattern, false));
    $countPattern = count($patternParts);
    $patternOffset = 0;

    # /test/param/optional/123
    $urlParts = explode("/", $url);
    $countUrl = count($urlParts);
    $urlOffset = 0;

    $params = [];
    for (; $patternOffset < $countPattern; $patternOffset++) {

      if (!preg_match("/^{.*}$/", $patternParts[$patternOffset])) {

        // not a parameter? check if it matches
        if ($urlOffset >= $countUrl || $urlParts[$urlOffset] !== $patternParts[$patternOffset]) {
          return false;
        }

        $urlOffset++;

      } else {

        // we got a parameter here
        $paramDefinition = explode(":", substr($patternParts[$patternOffset], 1, -1));
        $paramName = array_shift($paramDefinition);
        $paramType = array_shift($paramDefinition);
        $paramOptional = endsWith($paramType, "?");
        if ($paramOptional) {
          $paramType = substr($paramType, 0, -1);
        }

        $paramType = self::parseParamType($paramType);
        if ($urlOffset >= $countUrl || $urlParts[$urlOffset] === "") {
          if ($paramOptional) {
            $param = $urlParts[$urlOffset] ?? null;
            if ($param !== null && $paramType !== null && Parameter::parseType($param) !== $paramType) {
              return false;
            }

            $params[$paramName] = $param;
            if ($urlOffset < $countUrl) {
              $urlOffset++;
            }
          } else {
            return false;
          }
        } else {
          $param = $urlParts[$urlOffset];
          if ($paramType !== null && Parameter::parseType($param) !== $paramType) {
            return false;
          }

          $params[$paramName] = $param;
          $urlOffset++;
        }
      }
    }

    if ($urlOffset !== $countUrl && $this->exact) {
      return false;
    }

    return $params;
  }
}