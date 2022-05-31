<?php

include_once 'core/core.php';
include_once 'core/datetime.php';
include_once 'core/constants.php';

define("WEBROOT", realpath("."));

if (is_file("MAINTENANCE") && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
  http_response_code(503);
  serveStatic(WEBROOT, "/static/maintenance.html");
  die();
}

use Api\Request;
use Configuration\Configuration;
use Objects\Router;

if (!is_readable(getClassPath(Configuration::class))) {
  header("Content-Type: application/json");
  die(json_encode(array( "success" => false, "msg" => "Configuration class is not readable, check permissions before proceeding." )));
}

$config = new Configuration();
$user   = new Objects\User($config);
$sql    = $user->getSQL();
$settings = $config->getSettings();
$installation = !$sql || ($sql->isConnected() && !$settings->isInstalled());

// API routes, prefix: /api/
// TODO: move this to Router?
if (isset($_GET["api"]) && is_string($_GET["api"])) {
  $isApiResponse = true;
  if ($installation) {
    $response = createError("Not installed");
  } else {
    $apiFunction = $_GET["api"];
    if (empty($apiFunction) || $apiFunction === "/") {
      $document = new \Elements\TemplateDocument($user, "swagger.twig");
      $response = $document->getCode();
      $isApiResponse = false;
    } else if(!preg_match("/[a-zA-Z]+(\/[a-zA-Z]+)*/", $apiFunction)) {
      http_response_code(400);
      $response = createError("Invalid Method");
    } else {
      $apiFunction = array_filter(array_map('ucfirst', explode("/", $apiFunction)));
      if (count($apiFunction) > 1) {
        $parentClass = "\\Api\\" . reset($apiFunction) . "API";
        $apiClass = "\\Api\\" . implode("\\", $apiFunction);
      } else {
        $apiClass = "\\Api\\" . implode("\\", $apiFunction);
        $parentClass = $apiClass;
      }

      try {
        $file = getClassPath($parentClass);
        if(!file_exists($file) || !class_exists($parentClass) || !class_exists($apiClass)) {
          http_response_code(404);
          $response = createError("Not found");
        } else {
          $parentClass = new ReflectionClass($parentClass);
          $apiClass = new ReflectionClass($apiClass);
          if(!$apiClass->isSubclassOf(Request::class) || !$apiClass->isInstantiable()) {
            http_response_code(400);
            $response = createError("Invalid Method");
          } else {
            $request = $apiClass->newInstanceArgs(array($user, true));
            $success = $request->execute();
            $msg = $request->getLastError();
            $response = $request->getJsonResult();
          }
        }
      } catch (ReflectionException $e) {
        $response = createError("Error instantiating class: $e");
      }
    }

    if ($isApiResponse) {
      header("Content-Type: application/json");
    } else {
      header("Content-Type: text/html");
    }
  }
} else {

  // all other routes
  $requestedUri = $_GET["site"] ?? $_SERVER["REQUEST_URI"];
  $requestedUri = Router::cleanURL($requestedUri);

  if ($installation) {
    if ($requestedUri !== "" && $requestedUri !== "index.php") {
      $response = "Redirecting to <a href=\"/\">/</a>";
      header("Location: /");
    } else {
      $document = new Documents\Install($user);
      $response = $document->getCode();
    }
  } else {

    $router = null;

    $routerCacheClass = '\Cache\RouterCache';
    $routerCachePath = getClassPath($routerCacheClass);
    if (is_file($routerCachePath)) {
      @include_once $routerCachePath;
      if (class_exists($routerCacheClass)) {
        $router = new $routerCacheClass($user);
      }
    }

    if ($router === null) {
      $req = new \Api\Routes\GenerateCache($user);
      if ($req->execute()) {
        $router = $req->getRouter();
      } else {
        $message = "Unable to generate router cache: " . $req->getLastError();
        $response = (new Router($user))->returnStatusCode(500, [ "message" => $message ]);
      }
    }

    if ($router !== null) {
      $response = $router->run($requestedUri);
    }

    $user->processVisit();
  }
}

$user->sendCookies();
die($response);