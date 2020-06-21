<?php

namespace Api {
  abstract class RoutesAPI extends Request {

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
  }
}

namespace Api\Routes {

  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\RoutesAPI;
  use Driver\SQL\Column\Column;
  use Driver\SQL\Condition\CondBool;
  use Driver\SQL\Condition\Regex;

  class Fetch extends RoutesAPI {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array());
    $this->loginRequired = true;
    $this->csrfTokenRequired = true;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

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
          "active"  => intval($row["active"]),
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

    public function execute($values = array()) {
      if(!parent::execute($values)) {
        return false;
      }

      $request = $this->getParam('request');
      if (!startsWith($request, '/')) {
        $request = "/$request";
      }

      $sql = $this->user->getSQL();

      $res = $sql
        ->select("uid", "request", "action", "target", "extra")
        ->from("Route")
        ->where(new CondBool("active"))
        ->where(new Regex($request, new Column("request")))
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

      $this->loginRequired = true;
      $this->csrfTokenRequired = true;
      $this->requiredGroup = USER_GROUP_ADMIN;
    }

    public function execute($values = array()) {
      if(!parent::execute($values)) {
        return false;
      }

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

    private function validateRoutes() {

      $this->routes = array();
      $keys = array(
        "request" => Parameter::TYPE_STRING,
        "action" => Parameter::TYPE_STRING,
        "target" => Parameter::TYPE_STRING,
        "extra"  => Parameter::TYPE_STRING,
        "active" => Parameter::TYPE_BOOLEAN
      );

      $actions = array(
        "redirect_temporary", "redirect_permanently", "static", "dynamic"
      );

      foreach($this->getParam("routes") as $index => $route) {
        foreach($keys as $key => $expectedType) {
          if (!array_key_exists($key, $route)) {
            return $this->createError("Route $index missing key: $key");
          }

          $value = $route[$key];
          $type = Parameter::parseType($value);
          if ($type !== $expectedType && ($key !== "active" || !is_null($value))) {
            $expectedTypeName = Parameter::names[$expectedType];
            $gotTypeName = Parameter::names[$type];
            return $this->createError("Route $index has invalid value for key: $key, expected: $expectedTypeName, got: $gotTypeName");
          }
        }

        $action = $route["action"];
        if (!in_array($action, $actions)) {
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

}

