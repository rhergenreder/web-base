<?php

namespace Core\Objects\Router;

use Core\API\Request;
use Core\Elements\TemplateDocument;
use ReflectionClass;
use ReflectionException;

class ApiRoute extends AbstractRoute {

  public function __construct() {
    parent::__construct("/api/{endpoint:?}/{method:?}", false);
  }

  private static function checkClass(string $className): bool {
    $classPath = getClassPath($className);
    return file_exists($classPath) && class_exists($className);
  }

  public function call(Router $router, array $params): string {
    if (empty($params["endpoint"])) {
      header("Content-Type: text/html");
      $document = new TemplateDocument($router, "swagger.twig");
      return $document->load();
    } else if (!preg_match("/[a-zA-Z]+/", $params["endpoint"])) {
      http_response_code(400);
      $response = createError("Invalid Method");
    } else {
      $apiEndpoint = ucfirst($params["endpoint"]);
      $isNestedAPI = !empty($params["method"]);
      if ($isNestedAPI) {
        $apiMethod = ucfirst($params["method"]);
        $parentClass = "\\API\\${apiEndpoint}API";
        $apiClass = "\\API\\${apiEndpoint}\\${apiMethod}";
      } else {
        $apiClass = "\\API\\${apiEndpoint}";
        $parentClass = $apiClass;
      }

      try {
        $classFound = False;

        // first: check if the parent class exists, for example:
        // /stats => Stats.class.php
        // /mail/send => MailAPI.class.php
        foreach (["Site", "Core"] as $module) {
          if ($this->checkClass("\\$module$parentClass")) {
            if (!$isNestedAPI || class_exists("\\$module$apiClass")) {
              $classFound = true;
              $apiClass = "\\$module$apiClass";
              break;
            }
          }
        }

        if ($classFound) {
          $apiClass = new ReflectionClass($apiClass);
          if (!$apiClass->isSubclassOf(Request::class) || !$apiClass->isInstantiable()) {
            http_response_code(400);
            $response = createError("Invalid Method");
          } else {
            $request = $apiClass->newInstanceArgs(array($router->getContext(), true));
            $success = $request->execute();
            $response = $request->getResult();
            $response["success"] = $success;
            $response["msg"] = $request->getLastError();
          }
        } else {
          http_response_code(404);
          $response = createError("Not found");
        }
      } catch (ReflectionException $e) {
        http_response_code(500);
        $response = createError("Error instantiating class: $e");
      }
    }

    header("Content-Type: application/json");
    return json_encode($response);
  }
}