<?php

namespace Core\API {

  use Core\API\Routes\GenerateCache;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Route;
  use Core\Objects\Router\ApiRoute;

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
      } else if ($route instanceof ApiRoute) {
        return $this->createError("This route cannot be modified");
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
  use Core\Driver\SQL\Query\Insert;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Group;
  use Core\Objects\DatabaseEntity\Route;
  use Core\Objects\Router\ApiRoute;
  use Core\Objects\Router\EmptyRoute;
  use Core\Objects\Router\Router;

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
        $this->result["routes"] = $routes;
      }

      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN], "Allows users to fetch site routing", true);
    }
  }

  class Get extends RoutesAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "id" => new Parameter("id", Parameter::TYPE_INT)
      ]);
    }

    public function _execute(): bool {
      $sql = $this->context->getSQL();
      $routeId = $this->getParam("id");

      $route = Route::find($sql, $routeId);
      $this->lastError = $sql->getLastError();
      $this->success = ($route !== FALSE);

      if ($this->success) {
        if ($route === null) {
          return $this->createError("Route not found");
        } else {
          $this->result["route"] = $route;
        }
      }

      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(
        self::getEndpoint(),
        [Group::ADMIN, Group::MODERATOR],
        "Allows users to fetch a single route",
        true
      );
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

      if ($this->success) {
        $this->result["routeId"] = $route->getId();
      }

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
    }

    public function _execute(): bool {

      $sql = $this->context->getSQL();
      $id = $this->getParam("id");
      $route = Route::find($sql, $id);
      if ($route === false) {
        return $this->createError("Error fetching route: " . $sql->getLastError());
      } else if ($route === null) {
        return $this->createError("Route not found");
      } else if ($route instanceof ApiRoute) {
        return $this->createError("This route cannot be deleted");
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
      $insert->addRow(self::getEndpoint(),
        [Group::ADMIN, Group::SUPPORT],
        "Allows users to regenerate the routing cache", true
      );
    }
  }

  class Check extends RoutesAPI {
    public function __construct(Context $context, bool $externalCall) {
      parent::__construct($context, $externalCall, [
        "pattern" => new StringType("pattern", 128),
        "path" => new StringType("path"),
        "exact" => new Parameter("exact", Parameter::TYPE_BOOLEAN, true, true)
      ]);
    }

    protected function _execute(): bool {
      $path = $this->getParam("path");
      $pattern = $this->getParam("pattern");
      $exact = $this->getParam("exact");
      $route = new EmptyRoute($pattern, $exact, "");
      $this->result["match"] = $route->match($path);
      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow("routes/check",
        [Group::ADMIN, Group::MODERATOR],
        "Users with this permission can see, if a route pattern is matched with the given path for debugging purposes",
        true
      );
    }
  }
}

