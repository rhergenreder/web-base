<?php

namespace Core\API {

  use Core\API\Routes\GenerateCache;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Route;

  abstract class RoutesAPI extends Request {

    const ROUTER_CACHE_CLASS = "\\Site\\Cache\\RouterCache";

    protected string $routerCachePath;

    public function __construct(Context $context, bool $externalCall, array $params) {
      parent::__construct($context, $externalCall, $params);
      $this->routerCachePath = WEBROOT . DIRECTORY_SEPARATOR . getClassPath(self::ROUTER_CACHE_CLASS);
    }

    protected function toggleRoute(int $id, bool $active): bool {
      $sql = $this->context->getSQL();
      $route = Route::find($sql, $id);
      if ($route === false) {
        return false;
      } else if ($route === null) {
        return $this->createError("Route not found");
      }

      $route->setActive($active);
      $this->success = $route->save($sql, ["active"]);
      $this->lastError = $sql->getLastError();
      return $this->success && $this->regenerateCache();
    }

    protected function regenerateCache(): bool {
      $req = new GenerateCache($this->context);
      $this->success = $req->execute();
      $this->lastError = $req->getLastError();
      return $this->success;
    }

    protected function createRoute(string $type, string $pattern, string $target,
                                   ?string $extra, bool $exact, bool $active = true): ?Route {

      $routeClass = Route::ROUTE_TYPES[$type] ?? null;
      if (!$routeClass) {
        $this->createError("Invalid type: $type");
        return null;
      }

      try {
        $routeClass = new \ReflectionClass($routeClass);
        $routeObj = $routeClass->newInstance($pattern, $exact, $target);
        $routeObj->setExtra($extra);
        $routeObj->setActive($active);
        return $routeObj;
      } catch (\ReflectionException $exception) {
        $this->createError("Error instantiating route class: " . $exception->getMessage());
        return null;
      }
    }
  }
}

namespace Core\API\Routes {

  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\API\RoutesAPI;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Condition\CondBool;
  use Core\Driver\SQL\Query\Insert;
  use Core\Driver\SQL\Query\StartTransaction;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Group;
  use Core\Objects\DatabaseEntity\Route;
  use Core\Objects\Router\DocumentRoute;
  use Core\Objects\Router\RedirectPermanentlyRoute;
  use Core\Objects\Router\RedirectRoute;
  use Core\Objects\Router\RedirectTemporaryRoute;
  use Core\Objects\Router\Router;
  use Core\Objects\Router\StaticFileRoute;

  class Fetch extends RoutesAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array());
    }

    public function _execute(): bool {
      $sql = $this->context->getSQL();

      $routes = Route::findAll($sql);
      $this->lastError = $sql->getLastError();
      $this->success = ($routes !== FALSE);

      if ($this->success) {
        $this->result["routes"] = [];
        foreach ($routes as $route) {
          $this->result["routes"][$route->getId()] = $route->jsonSerialize();
        }
      }

      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN], "Allows users to fetch site routing", true);
    }
  }

  class Save extends RoutesAPI {

    private array $routes;

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'routes' => new Parameter('routes', Parameter::TYPE_ARRAY, false)
      ));
    }

    public function _execute(): bool {
      if (!$this->validateRoutes()) {
        return false;
      }

      $sql = $this->context->getSQL();
      $sql->startTransaction();

      // DELETE old rules;
      $this->success = ($sql->truncate("Route")->execute() !== FALSE);
      $this->lastError = $sql->getLastError();

      // INSERT new routes
      if ($this->success) {
        $insertStatement = Route::getHandler($sql)->getInsertQuery($this->routes);
        $this->success = ($insertStatement->execute() !== FALSE);
        $this->lastError = $sql->getLastError();
      }

      if ($this->success) {
        $sql->commit();
        return $this->regenerateCache();
      } else {
        $sql->rollback();
        return false;
      }
    }

    private function validateRoutes(): bool {

      $this->routes = array();
      $keys = array(
        "id" => Parameter::TYPE_INT,
        "pattern" => [Parameter::TYPE_STRING, Parameter::TYPE_INT],
        "type" => Parameter::TYPE_STRING,
        "target" => Parameter::TYPE_STRING,
        "extra" => Parameter::TYPE_STRING,
        "active" => Parameter::TYPE_BOOLEAN,
        "exact" => Parameter::TYPE_BOOLEAN,
      );

      foreach ($this->getParam("routes") as $index => $route) {
        foreach ($keys as $key => $expectedType) {
          if (!array_key_exists($key, $route)) {
            if ($key !== "id") {  // id is optional
              return $this->createError("Route $index missing key: $key");
            } else {
              continue;
            }
          }

          $value = $route[$key];
          $type = Parameter::parseType($value);
          if (!is_array($expectedType)) {
            $expectedType = [$expectedType];
          }

          if (!in_array($type, $expectedType)) {
            if (count($expectedType) > 0) {
              $expectedTypeName = "expected: " . Parameter::names[$expectedType];
            } else {
              $expectedTypeName = "expected one of: " . implode(",", array_map(
                function ($type) {
                  return Parameter::names[$type];
                }, $expectedType));
            }
            $gotTypeName = Parameter::names[$type];
            return $this->createError("Route $index has invalid value for key: $key, $expectedTypeName, got: $gotTypeName");
          }
        }

        $type = $route["type"];
        if (!isset(Route::ROUTE_TYPES[$type])) {
          return $this->createError("Invalid type: $type");
        }

        if (empty($route["pattern"])) {
          return $this->createError("Pattern cannot be empty.");
        }

        if (empty($route["target"])) {
          return $this->createError("Target cannot be empty.");
        }

        $this->routes[] = $route;
      }

      return true;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN], "Allows users to save the site routing", true);
    }
  }

  class Add extends RoutesAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "pattern" => new StringType("pattern", 128),
        "type" => new StringType("type"),
        "target" => new StringType("target", 128),
        "extra" => new StringType("extra", 64, true, ""),
        "exact" => new Parameter("exact", Parameter::TYPE_BOOLEAN),
        "active" => new Parameter("active", Parameter::TYPE_BOOLEAN, true, true),
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $pattern = $this->getParam("pattern");
      $type = $this->getParam("type");
      $target = $this->getParam("target");
      $extra = $this->getParam("extra");
      $exact = $this->getParam("exact");
      $active = $this->getParam("active");
      $route = $this->createRoute($type, $pattern, $target, $extra, $exact, $active);
      if ($route === null) {
        return false;
      }

      $sql = $this->context->getSQL();
      $this->success = $route->save($sql) !== false;
      $this->lastError = $sql->getLastError();
      return $this->success && $this->regenerateCache();
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN], "Allows users to add new routes", true);
    }
  }

  class Update extends RoutesAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "id" => new Parameter("id", Parameter::TYPE_INT),
        "pattern" => new StringType("pattern", 128),
        "type" => new StringType("type"),
        "target" => new StringType("target", 128),
        "extra" => new StringType("extra", 64, true, ""),
        "exact" => new Parameter("exact", Parameter::TYPE_BOOLEAN),
        "active" => new Parameter("active", Parameter::TYPE_BOOLEAN, true, true),
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $id = $this->getParam("id");
      $sql = $this->context->getSQL();
      $route = Route::find($sql, $id);
      if ($route === false) {
        return $this->createError("Error fetching route: " . $sql->getLastError());
      } else if ($route === null) {
        return $this->createError("Route not found");
      }

      $target = $this->getParam("target");
      $extra = $this->getParam("extra");
      $type = $this->getParam("type");
      $pattern = $this->getParam("pattern");
      $exact = $this->getParam("exact");
      $active = $this->getParam("active");
      if ($route->getType() !== $type) {
        if (!$route->delete($sql)) {
          return false;
        } else {
          $route = $this->createRoute($type, $pattern, $target, $extra, $exact, $active);
          if ($route === null) {
            return false;
          }
        }
      } else {
        $route->setPattern($pattern);
        $route->setActive($active);
        $route->setExtra($extra);
        $route->setTarget($target);
        $route->setExact($exact);
      }

      $this->success = $route->save($sql) !== false;
      $this->lastError = $sql->getLastError();
      return $this->success && $this->regenerateCache();
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN], "Allows users to update existing routes", true);
    }
  }

  class Remove extends RoutesAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "id" => new Parameter("id", Parameter::TYPE_INT)
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $sql = $this->context->getSQL();
      $id = $this->getParam("id");
      $route = Route::find($sql, $id);
      if ($route === false) {
        return $this->createError("Error fetching route: " . $sql->getLastError());
      } else if ($route === null) {
        return $this->createError("Route not found");
      }

      $this->success = $route->delete($sql) !== false;
      $this->lastError = $sql->getLastError();
      return $this->success && $this->regenerateCache();
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN], "Allows users to remove routes", true);
    }
  }

  class Enable extends RoutesAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "id" => new Parameter("id", Parameter::TYPE_INT)
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {
      $id = $this->getParam("id");
      return $this->toggleRoute($id, true);
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN], "Allows users to enable a route", true);
    }
  }

  class Disable extends RoutesAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "id" => new Parameter("id", Parameter::TYPE_INT)
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {
      $id = $this->getParam("id");
      return $this->toggleRoute($id, false);
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN], "Allows users to disable a route", true);
    }
  }

  class GenerateCache extends RoutesAPI {

    private ?Router $router;

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, []);
      $this->isPublic = false;
      $this->router = null;
    }

    protected function _execute(): bool {
      $sql = $this->context->getSQL();
      $routes = Route::findBy(Route::createBuilder($sql, false)
        ->whereTrue("active")
        ->orderBy("id")
        ->ascending());

      $this->success = $routes !== false;
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      }

      $this->router = new Router($this->context);
      foreach ($routes as $route) {
        $this->router->addRoute($route);
      }

      $this->success = $this->router->writeCache($this->routerCachePath);
      if (!$this->success) {
        return $this->createError("Error saving router cache file: " . $this->routerCachePath);
      }

      return $this->success;
    }

    public function getRouter(): ?Router {
      return $this->router;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN, Group::SUPPORT], "Allows users to regenerate the routing cache", true);
    }
  }
}

