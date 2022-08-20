<?php

include_once 'core/core.php';
include_once 'core/datetime.php';
include_once 'core/constants.php';

define("WEBROOT", realpath("."));

if (is_file("MAINTENANCE") && !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
  http_response_code(503);
  \Objects\Router\StaticFileRoute::serveStatic(WEBROOT . "/static/maintenance.html");
  die();
}

use Configuration\Configuration;
use Objects\Router\Router;

if (!is_readable(getClassPath(Configuration::class))) {
  header("Content-Type: application/json");
  die(json_encode([ "success" => false, "msg" => "Configuration class is not readable, check permissions before proceeding." ]));
}

$context = new \Objects\Context();
$sql = $context->initSQL();
$settings = $context->getSettings();
$context->parseCookies();

$installation = !$sql || ($sql->isConnected() && !$settings->isInstalled());
$requestedUri = $_GET["site"] ?? $_GET["api"] ?? $_SERVER["REQUEST_URI"];

if ($installation) {
  $requestedUri = Router::cleanURL($requestedUri);
  if ($requestedUri !== "" && $requestedUri !== "index.php") {
    $response = "Redirecting to <a href=\"/\">/</a>";
    header("Location: /");
  } else {
    $document = new Documents\Install(new Router($context));
    $response = $document->load();
  }
} else {

  $router = null;
  $routerCacheClass = '\Cache\RouterCache';
  $routerCachePath = getClassPath($routerCacheClass);
  if (is_file($routerCachePath)) {
    @include_once $routerCachePath;
    if (class_exists($routerCacheClass)) {
      $router = new $routerCacheClass($context);
    }
  }

  if ($router === null) {
    $req = new \Api\Routes\GenerateCache($context);
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
      $response = $router->run($requestedUri);
    }
  }

  $context->processVisit();
}

$context->sendCookies();
die($response);