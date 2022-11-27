<?php

namespace Core\Objects\DatabaseEntity;

use Core\API\Parameter\Parameter;
use Core\Objects\DatabaseEntity\Attribute\DefaultValue;
use Core\Objects\DatabaseEntity\Attribute\ExtendingEnum;
use Core\Objects\DatabaseEntity\Attribute\MaxLength;
use Core\Objects\DatabaseEntity\Attribute\Unique;
use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;
use Core\Objects\Router\DocumentRoute;
use Core\Objects\Router\RedirectRoute;
use Core\Objects\Router\Router;
use Core\Objects\Router\StaticFileRoute;

abstract class Route extends DatabaseEntity {

  const PARAMETER_PATTERN = "/^{([^:]+)(:(.*?)(\?)?)?}$/";
  const ROUTE_TYPES = [
    "redirect_temporary" => RedirectRoute::class,
    "redirect_permanently" => RedirectRoute::class,
    "static" => StaticFileRoute::class,
    "dynamic" => DocumentRoute::class
  ];

  #[MaxLength(128)]
  #[Unique]
  private string $pattern;

  #[ExtendingEnum(self::ROUTE_TYPES)]
  private string $type;

  #[MaxLength(128)]
  private string $target;

  #[MaxLength(64)]
  protected ?string $extra;

  #[DefaultValue(true)]
  private bool $active;

  private bool $exact;

  public function __construct(string $type, string $pattern, string $target, bool $exact = true) {
    parent::__construct();
    $this->target = $target;
    $this->pattern = $pattern;
    $this->exact = $exact;
    $this->type = $type;
    $this->active = true;
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

  public function getTarget(): string {
    return $this->target;
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

  public function jsonSerialize(): array {
    return [
      "id" => $this->getId(),
      "pattern" => $this->pattern,
      "type" => $this->type,
      "target" => $this->target,
      "extra" => $this->extra,
      "exact" => $this->exact,
      "active" => $this->active,
    ];
  }
}