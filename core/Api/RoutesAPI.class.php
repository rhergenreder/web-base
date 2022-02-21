<?php

namespace Api {

  use Driver\SQL\Condition\Compare;

  abstract class RoutesAPI extends Request {

    const ACTIONS = array("redirect_temporary", "redirect_permanently", "static", "dynamic");

    protected function formatRegex(string $input, bool $append) : string {
      $start = startsWith($input, "^");
      $end = endsWith($input, "$");
      if ($append) {
        if (!$start) $input = "^$input";
        if (!$end) $input = "$input$";
      } else {
        if ($start) $input = substr($input, 1);
        if ($end) $input = substr($input, 0, strlen($input)-1);
      }

      return $input;
    }

    protected function routeExists($uid): bool {
      $sql = $this->user->getSQL();
      $res = $sql->select($sql->count())
        ->from("Route")
        ->where(new Compare("uid", $uid))
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

      $sql = $this->user->getSQL();
      $this->success = $sql->update("Route")
        ->set("active", $active)
        ->where(new Compare("uid", $uid))
        ->execute();

      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }
}

namespace Api\Routes {

  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\RoutesAPI;
  use Driver\SQL\Column\Column;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondBool;
  use Driver\SQL\Condition\CondRegex;
  use Objects\User;

  class Fetch extends RoutesAPI {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array());
  }

  public function _execute(): bool {
    $sql = $this->user->getSQL();

    $res = $sql
      ->select("uid", "request", "action", "target", "extra", "active")
      ->from("Route")
      ->orderBy("uid")
      ->ascending()
      ->execute();

    $this->lastError = $sql->getLastError();
    $this->success = ($res !== FALSE);

    if ($this->success) {
      $routes = array();
      foreach($res as $row) {
        $routes[] = array(
          "uid"     => intval($row["uid"]),
          "request" => $this->formatRegex($row["request"], false),
          "action"  => $row["action"],
          "target"  => $row["target"],
          "extra"   => $row["extra"] ?? "",
          "active"  => intval($sql->parseBool($row["active"])),
        );
      }

      $this->result["routes"] = $routes;
    }

    return $this->success;
  }
}

  class Find extends RoutesAPI {

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'request' => new StringType('request', 128, true, '/')
      ));

      $this->isPublic = false;
    }

    public function _execute(): bool {
      $request = $this->getParam('request');
      if (!startsWith($request, '/')) {
        $request = "/$request";
      }

      $sql = $this->user->getSQL();

      $res = $sql
        ->select("uid", "request", "action", "target", "extra")
        ->from("Route")
        ->where(new CondBool("active"))
        ->where(new CondRegex($request, new Column("request")))
        ->orderBy("uid")->ascending()
        ->limit(1)
        ->execute();

      $this->lastError = $sql->getLastError();
      $this->success = ($res !== FALSE);

      if ($this->success) {
        if (!empty($res)) {
          $row = $res[0];
          $this->result["route"] = array(
            "uid"     => intval($row["uid"]),
            "request" => $row["request"],
            "action"  => $row["action"],
            "target"  => $row["target"],
            "extra"   => $row["extra"]
          );
        } else {
          $this->result["route"] = NULL;
        }
      }

      return $this->success;
    }
  }

  class Save extends RoutesAPI {

    private array $routes;

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'routes' => new Parameter('routes',Parameter::TYPE_ARRAY, false)
      ));
    }

    public function _execute(): bool {
      if (!$this->validateRoutes()) {
        return false;
      }

      $sql = $this->user->getSQL();

      // DELETE old rules
      $this->success = ($sql->truncate("Route")->execute() !== FALSE);
      $this->lastError = $sql->getLastError();

      // INSERT new routes
      if ($this->success) {
        $stmt = $sql->insert("Route", array("request", "action", "target", "extra", "active"));

        foreach($this->routes as $route) {
          $stmt->addRow($route["request"], $route["action"], $route["target"], $route["extra"], $route["active"]);
        }
        $this->success = ($stmt->execute() !== FALSE);
        $this->lastError = $sql->getLastError();
      }

      return $this->success;
    }

    private function validateRoutes(): bool {

      $this->routes = array();
      $keys = array(
        "request" => Parameter::TYPE_STRING,
        "action" => Parameter::TYPE_STRING,
        "target" => Parameter::TYPE_STRING,
        "extra"  => Parameter::TYPE_STRING,
        "active" => Parameter::TYPE_BOOLEAN
      );

      foreach($this->getParam("routes") as $index => $route) {
        foreach($keys as $key => $expectedType) {
          if (!array_key_exists($key, $route)) {
            return $this->createError("Route $index missing key: $key");
          }

          $value = $route[$key];
          $type = Parameter::parseType($value);
          if ($type !== $expectedType) {
            $expectedTypeName = Parameter::names[$expectedType];
            $gotTypeName = Parameter::names[$type];
            return $this->createError("Route $index has invalid value for key: $key, expected: $expectedTypeName, got: $gotTypeName");
          }
        }

        $action = $route["action"];
        if (!in_array($action, self::ACTIONS)) {
          return $this->createError("Invalid action: $action");
        }

        if(empty($route["request"])) {
          return $this->createError("Request cannot be empty.");
        }

        if(empty($route["target"])) {
          return $this->createError("Target cannot be empty.");
        }

        // add start- and end pattern for database queries
        $route["request"] = $this->formatRegex($route["request"], true);
        $this->routes[] = $route;
      }

      return true;
    }
  }

  class Add extends RoutesAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "request" => new StringType("request", 128),
        "action" => new StringType("action"),
        "target" => new StringType("target", 128),
        "extra"  => new StringType("extra", 64, true, ""),
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $request = $this->formatRegex($this->getParam("request"), true);
      $action = $this->getParam("action");
      $target = $this->getParam("target");
      $extra = $this->getParam("extra");

      if (!in_array($action, self::ACTIONS)) {
        return $this->createError("Invalid action: $action");
      }

      $sql = $this->user->getSQL();
      $this->success = $sql->insert("Route", ["request", "action", "target", "extra"])
        ->addRow($request, $action, $target, $extra)
        ->execute();

      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }

  class Update extends RoutesAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "uid" => new Parameter("uid", Parameter::TYPE_INT),
        "request" => new StringType("request", 128),
        "action" => new StringType("action"),
        "target" => new StringType("target", 128),
        "extra"  => new StringType("extra", 64, true, ""),
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $uid = $this->getParam("uid");
      if (!$this->routeExists($uid)) {
        return false;
      }

      $request = $this->formatRegex($this->getParam("request"), true);
      $action = $this->getParam("action");
      $target = $this->getParam("target");
      $extra = $this->getParam("extra");
      if (!in_array($action, self::ACTIONS)) {
        return $this->createError("Invalid action: $action");
      }

      $sql = $this->user->getSQL();
      $this->success = $sql->update("Route")
        ->set("request", $request)
        ->set("action", $action)
        ->set("target", $target)
        ->set("extra", $extra)
        ->where(new Compare("uid", $uid))
        ->execute();

      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }

  class Remove extends RoutesAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "uid" => new Parameter("uid", Parameter::TYPE_INT)
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $uid = $this->getParam("uid");
      if (!$this->routeExists($uid)) {
        return false;
      }

      $sql = $this->user->getSQL();
      $this->success = $sql->delete("Route")
        ->where(new Compare("uid", $uid))
        ->execute();

      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }

  class Enable extends RoutesAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "uid" => new Parameter("uid", Parameter::TYPE_INT)
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $uid = $this->getParam("uid");
      return $this->toggleRoute($uid, true);
    }
  }

  class Disable extends RoutesAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        "uid" => new Parameter("uid", Parameter::TYPE_INT)
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {

      $uid = $this->getParam("uid");
      return $this->toggleRoute($uid, false);
    }
  }
}

