<?php

namespace Core\Objects\Router;

use Core\API\Parameter\Parameter;

abstract class AbstractRoute {

  const PARAMETER_PATTERN = "/^{([^:]+)(:(.*?)(\?)?)?}$/";

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

  public function getClass(): \ReflectionClass {
    return new \ReflectionClass($this);
  }

  public function generateCache(): string {
    $reflection = $this->getClass();
    $className = $reflection->getShortName();
    $args = implode(", ", array_map(function ($arg) {
      return var_export($arg, true);
    }, $this->getArgs()));
    return "new $className($args)";
  }

  public function match(string $url) {

    # /test/{abc}/{param:?}/{xyz:int}/{aaa:int?}
    $patternParts = explode("/", Router::cleanURL($this->pattern, false));
    $countPattern = count($patternParts);
    $patternOffset = 0;

    # /test/param/optional/123
    $urlParts = explode("/", Router::cleanURL($url));
    $countUrl = count($urlParts);
    $urlOffset = 0;

    $params = [];
    for (; $patternOffset < $countPattern; $patternOffset++) {
      if (!preg_match(self::PARAMETER_PATTERN, $patternParts[$patternOffset], $match)) {

        // not a parameter? check if it matches
        if ($urlOffset >= $countUrl || $urlParts[$urlOffset] !== $patternParts[$patternOffset]) {
          return false;
        }

        $urlOffset++;
      } else {

        // we got a parameter here
        $paramName = $match[1];
        if (isset($match[2])) {
          $paramType = self::parseParamType($match[3]) ?? Parameter::TYPE_MIXED;
          $paramOptional = !empty($match[4] ?? null);
        } else {
          $paramType = Parameter::TYPE_MIXED;
          $paramOptional = false;
        }

        $parameter = new Parameter($paramName, $paramType, $paramOptional);
        if ($urlOffset >= $countUrl || $urlParts[$urlOffset] === "") {
          if ($parameter->optional) {
            $value = $urlParts[$urlOffset] ?? null;
            if ($value === null || $value === "") {
              $params[$paramName] = null;
            } else {
              if (!$parameter->parseParam($value)) {
                return false;
              } else {
                $params[$paramName] = $parameter->value;
              }
            }

            if ($urlOffset < $countUrl) {
              $urlOffset++;
            }
          } else {
            return false;
          }
        } else {
          $value = $urlParts[$urlOffset];
          if (!$parameter->parseParam($value)) {
            return false;
          } else {
            $params[$paramName] = $parameter->value;
            $urlOffset++;
          }
        }
      }
    }

    if ($urlOffset !== $countUrl && $this->exact) {
      return false;
    }

    return $params;
  }

  public function getUrl(array $parameters = []): string {
    $patternParts = explode("/", Router::cleanURL($this->pattern, false));

    foreach ($patternParts as $i => $part) {
      if (preg_match(self::PARAMETER_PATTERN, $part, $match)) {
        $paramName = $match[1];
        $patternParts[$i] = $parameters[$paramName] ?? null;
      }
    }

    return "/" . implode("/", array_filter($patternParts));
  }

  public function getParameterNames(): array {
    $parameterNames = [];
    $patternParts = explode("/", Router::cleanURL($this->pattern, false));

    foreach ($patternParts as $part) {
      if (preg_match(self::PARAMETER_PATTERN, $part, $match)) {
        $parameterNames[] = $match[1];
      }
    }

    return $parameterNames;
  }
}