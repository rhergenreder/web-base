<?php

namespace Api {

  use Api\Routes\GenerateCache;
  use Driver\SQL\Condition\Compare;
  use Objects\Context;

  abstract class RoutesAPI extends Request {

    const ACTIONS = array("redirect_temporary", "redirect_permanently", "static", "dynamic");
    const ROUTER_CACHE_CLASS = "\\Cache\\RouterCache";

    protected string $routerCachePath;

    public function __construct(Context $context, bool $externalCall, array $params) {
      parent::__construct($context, $externalCall, $params);
      $this->routerCachePath = getClassPath(self::ROUTER_CACHE_CLASS);
    }

    protected function routeExists($uid): bool {
      $sql = $this->context->getSQL();
      $res = $sql->select($sql->count())
        ->from("Route")
        ->where(new Compare("id", $uid))
        ->execute();

      $this->success = ($res !== false);
      $this->lastError = $sql->getLastError();
      if ($this->success) {
        if ($res[0]["count"] === 0) {
          return $this->createError("Route not found");
        }
      }

      return $this->success;
    }

    protected function toggleRoute($uid, $active): bool {
      if (!$this->routeExists($uid)) {
        return false;
      }

      $sql = $this->context->getSQL();
      $this->success = $sql->update("Route")
        ->set("active", $active)
        ->where(new Compare("id", $uid))
        ->execute();

      $this->lastError = $sql->getLastError();
      $this->success = $this->success && $this->regenerateCache();
      return $this->success;
    }

    protected function regenerateCache(): bool {
      $req = new GenerateCache($this->context);
      $this->success = $req->execute();
      $this->lastError = $req->getLastError();
      return $this->success;
    }
  }
}

namespace Api\Routes {

  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\RoutesAPI;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondBool;
  use Objects\Context;
  use Objects\Router\DocumentRoute;
  use Objects\Router\RedirectRoute;
  use Objects\Router\Router;
  use Objects\Router\StaticFileRoute;

  class Fetch extends RoutesAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array());
    }

    public function _execute(): bool {
      $sql = $this->context->getSQL();

      $res = $sql
        ->select("id", "request", "action", "target", "extra", "active", "exact")
        ->from("Route")
        ->orderBy("id")
        ->ascending()
        ->execute();

      $this->lastError = $sql->getLastError();
      $this->success = ($res !== FALSE);

      if ($this->success) {
        $routes = array();
        foreach ($res as $row) {
          $routes[] = array(
            "id" => intval($row["id"]),
            "request" => $row["request"],
            "action" => $row["action"],
            "target" => $row["target"],
            "extra" => $row["extra"] ?? "",
            "active" => intval($sql->parseBool($row["active"])),
            "exact" => intval($sql->parseBool($row["exact"])),
          );
        }

        $this->result["routes"] = $routes;
      }

      return $this->success;
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

      // DELETE old rules
      $this->success = ($sql->truncate("Route")->execute() !== FALSE);
      $this->lastError = $sql->getLastError();

      // INSERT new routes
      if ($this->success) {
        $stmt = $sql->insert("Route", array("request", "action", "target", "extra", "active", "exact"));

        foreach ($this->routes as $route) {
          $stmt->addRow($route["request"], $route["action"], $route["target"], $route["extra"], $route["active"], $route["exact"]);
        }
        $this->success = ($stmt->execute() !== FALSE);
        $this->lastError = $sql->getLastError();
      }

      $this->success = $this->success && $this->regenerateCache();
      return $this->success;
    }

    private function validateRoutes(): bool {

      $this->routes = array();
      $keys = array(
        "request" => [Parameter::TYPE_STRING, Parameter::TYPE_INT],
        "action" => Parameter::TYPE_STRING,
        "target" => Parameter::TYPE_STRING,
        "extra" => Parameter::TYPE_STRING,
        "active" => Parameter::TYPE_BOOLEAN,
        "exact" => Parameter::TYPE_BOOLEAN,
      );

      foreach ($this->getParam("routes") as $index => $route) {
        foreach ($keys as $key => $expectedType) {
          if (!array_key_exists($key, $route)) {
            return $this->createError("Route $index missing key: $key");
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

        $action = $route["action"];
        if (!in_array($action, self::ACTIONS)) {
          return $this->createError("Invalid action: $action");
        }

        if (empty($route["request"])) {
          return $this->createError("Request cannot be empty.");
        }

        if (empty($route["target"])) {
          return $this->createError("Target cannot be empty.");
        }

        $this->routes[] = $route;
      }

      return true;
    }
  }

  class Add extends RoutesAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "request" => new StringType("request", 128),
        "action" => new StringType("action"),
        "target" => new StringType("target", 128),
        "extra" => new StringType("extra", 64, true, ""),
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $request = $this->getParam("request");
      $action = $this->getParam("action");
      $target = $this->getParam("target");
      $extra = $this->getParam("extra");

      if (!in_array($action, self::ACTIONS)) {
        return $this->createError("Invalid action: $action");
      }

      $sql = $this->context->getSQL();
      $this->success = $sql->insert("Route", ["request", "action", "target", "extra"])
        ->addRow($request, $action, $target, $extra)
        ->execute();

      $this->lastError = $sql->getLastError();
      $this->success = $this->success && $this->regenerateCache();
      return $this->success;
    }
  }

  class Update extends RoutesAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "id" => new Parameter("id", Parameter::TYPE_INT),
        "request" => new StringType("request", 128),
        "action" => new StringType("action"),
        "target" => new StringType("target", 128),
        "extra" => new StringType("extra", 64, true, ""),
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $id = $this->getParam("id");
      if (!$this->routeExists($id)) {
        return false;
      }

      $request = $this->getParam("request");
      $action = $this->getParam("action");
      $target = $this->getParam("target");
      $extra = $this->getParam("extra");
      if (!in_array($action, self::ACTIONS)) {
        return $this->createError("Invalid action: $action");
      }

      $sql = $this->context->getSQL();
      $this->success = $sql->update("Route")
        ->set("request", $request)
        ->set("action", $action)
        ->set("target", $target)
        ->set("extra", $extra)
        ->where(new Compare("id", $id))
        ->execute();

      $this->lastError = $sql->getLastError();
      $this->success = $this->success && $this->regenerateCache();
      return $this->success;
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

      $uid = $this->getParam("id");
      if (!$this->routeExists($uid)) {
        return false;
      }

      $sql = $this->context->getSQL();
      $this->success = $sql->delete("Route")
        ->where(new Compare("id", $uid))
        ->execute();

      $this->lastError = $sql->getLastError();
      $this->success = $this->success && $this->regenerateCache();
      return $this->success;
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
      $uid = $this->getParam("id");
      return $this->toggleRoute($uid, true);
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
      $uid = $this->getParam("id");
      return $this->toggleRoute($uid, false);
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
      $res = $sql
        ->select("id", "request", "action", "target", "extra", "exact")
        ->from("Route")
        ->where(new CondBool("active"))
        ->orderBy("id")->ascending()
        ->execute();

      $this->success = $res !== false;
      $this->lastError = $sql->getLastError();
      if (!$this->success) {
        return false;
      }

      $this->router = new Router($this->context);
      foreach ($res as $row) {
        $request = $row["request"];
        $target = $row["target"];
        $exact = $sql->parseBool($row["exact"]);
        switch ($row["action"]) {
          case "redirect_temporary":
            $this->router->addRoute(new RedirectRoute($request, $exact, $target, 307));
            break;
          case "redirect_permanently":
            $this->router->addRoute(new RedirectRoute($request, $exact, $target, 308));
            break;
          case "static":
            $this->router->addRoute(new StaticFileRoute($request, $exact, $target));
            break;
          case "dynamic":
            $extra = json_decode($row["extra"]) ?? [];
            $this->router->addRoute(new DocumentRoute($request, $exact, $target, ...$extra));
            break;
          default:
            break;
        }
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
  }
}

