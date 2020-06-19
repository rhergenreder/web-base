<?php

namespace Api\Routes;

use Api\Parameter\Parameter;
use \Api\Request;

class Save extends Request {

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

      $this->routes[] = $route;
    }

    return true;
  }
}