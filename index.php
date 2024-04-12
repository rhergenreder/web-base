<?php

include_once 'Core/core.php';
include_once 'Core/datetime.php';
include_once 'Core/constants.php';

define("WEBROOT", realpath("."));

if (is_file("MAINTENANCE") && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
  http_response_code(503);
  \Core\Objects\Router\StaticFileRoute::serveStatic(WEBROOT . "/static/maintenance.html");
  die();
}

use Core\Configuration\Configuration;
use Core\Objects\Context;
use Core\Objects\Router\Router;

if (!is_readable(getClassPath(Configuration::class))) {
  header("Content-Type: application/json");
  http_response_code(500);
  die(json_encode(createError("Configuration class is not readable, check permissions before proceeding.")));
}

$context = Context::instance();
$sql = $context->initSQL();
$settings = $context->getSettings();
$context->parseCookies();

$currentHostName = getCurrentHostName();

$installation = !$sql || ($sql->isConnected() && !$settings->isInstalled());
$requestedUri = $_GET["site"] ?? $_GET["api"] ?? $_SERVER["REQUEST_URI"];

if ($installation) {
  $requestedUri = Router::cleanURL($requestedUri);
  if ($requestedUri !== "" && $requestedUri !== "index.php") {
    $response = "Redirecting to <a href=\"/\">/</a>";
    header("Location: /");
  } else {
    $document = new \Core\Documents\Install(new Router($context));
    $response = $document->load();
  }
} else {

  $router = null;
  $routerCacheClass = '\Site\Cache\RouterCache';
  $routerCachePath = getClassPath($routerCacheClass);
  if (is_file($routerCachePath)) {
    @include_once $routerCachePath;
    if (class_exists($routerCacheClass)) {
      $router = new $routerCacheClass($context);
    }
  }

  if ($router === null) {
    $req = new \Core\API\Routes\GenerateCache($context);
    if ($req->execute()) {
      $router = $req->getRouter();
    } else {
      $message = "Unable to generate router cache: " . $req->getLastError();
      $response = (new Router($context))->returnStatusCode(500, [ "message" => $message ]);
    }
  }

  if ($router !== null) {

    if ((!isset($_GET["site"]) || $_GET["site"] === "/") && isset($_GET["error"]) &&
      is_string($_GET["error"]) && preg_match("/^\d+$/", $_GET["error"])) {
      $response = $router->returnStatusCode(intval($_GET["error"]));
    } else {
      try {
        $pathParams = [];
        $route = $router->run($requestedUri, $pathParams);
        if ($route === null) {
          $response = $router->returnStatusCode(404);
        } else if (!$settings->isTrustedDomain($currentHostName)) {
          $error = "Untrusted Origin. Adjust the 'trusted_domains' setting " .
            "to include the current host '$currentHostName' or contact the administrator to resolve this issue";
          if ($route instanceof \Core\Objects\Router\ApiRoute) {
            header("Content-Type: application/json");
            http_response_code(403);
            $response = json_encode(createError($error));
          } else {
            $response = $router->returnStatusCode(403, ["message" => $error]);
          }
        } else {
          $response = $route->call($router, $pathParams);
        }
      } catch (\Throwable $e) {
        http_response_code(500);
        $router->getLogger()->error($e->getMessage());
        $response = $router->returnStatusCode(500);
      }
    }
  } else {
    http_response_code(500);
    $response = "Router could not be instantiated.";
  }

  $context->processVisit();
}

$context->sendCookies();
die($response);