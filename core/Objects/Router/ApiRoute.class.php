<?php

namespace Objects\Router;

use Api\Request;
use ReflectionClass;
use ReflectionException;

class ApiRoute extends AbstractRoute {

  public function __construct() {
    parent::__construct("/api/{endpoint:?}/{method:?}", false);
  }

  public function call(Router $router, array $params): string {
    $user = $router->getUser();
    if (empty($params["endpoint"])) {
      header("Content-Type: text/html");
      $document = new \Elements\TemplateDocument($router, "swagger.twig");
      return $document->getCode();
    } else if (!preg_match("/[a-zA-Z]+/", $params["endpoint"])) {
      http_response_code(400);
      $response = createError("Invalid Method");
    } else {
      $apiEndpoint = ucfirst($params["endpoint"]);
      if (!empty($params["method"])) {
        $apiMethod = ucfirst($params["method"]);
        $parentClass = "\\Api\\${apiEndpoint}API";
        $apiClass = "\\Api\\${apiEndpoint}\\${apiMethod}";
      } else {
        $apiClass = "\\Api\\${apiEndpoint}";
        $parentClass = $apiClass;
      }

      try {
        $file = getClassPath($parentClass);
        if (!file_exists($file) || !class_exists($parentClass) || !class_exists($apiClass)) {
          http_response_code(404);
          $response = createError("Not found");
        } else {
          $apiClass = new ReflectionClass($apiClass);
          if(!$apiClass->isSubclassOf(Request::class) || !$apiClass->isInstantiable()) {
            http_response_code(400);
            $response = createError("Invalid Method");
          } else {
            $request = $apiClass->newInstanceArgs(array($user, true));
            $request->execute();
            $response = $request->getJsonResult();
          }
        }
      } catch (ReflectionException $e) {
        http_response_code(500);
        $response = createError("Error instantiating class: $e");
      }
    }

    header("Content-Type: application/json");
    return $response;
  }
}