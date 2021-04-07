<?php

include_once 'core/core.php';
include_once 'core/datetime.php';
include_once 'core/constants.php';

if (is_file("MAINTENANCE")) {
  http_response_code(503);
  $currentDir = dirname(__FILE__);
  serveStatic($currentDir, "/static/maintenance.html");
  die();
}

use Api\Request;
use Configuration\Configuration;
use Documents\Document404;
use Elements\Document;

if (!is_readable(getClassPath(Configuration::class))) {
  header("Content-Type: application/json");
  die(json_encode(array( "success" => false, "msg" => "Configuration class is not readable, check permissions before proceeding." )));
}

$config = new Configuration();
$user   = new Objects\User($config);
$sql    = $user->getSQL();
$settings = $config->getSettings();
$installation = !$sql || ($sql->isConnected() && !$settings->isInstalled());

if(isset($_GET["api"]) && is_string($_GET["api"])) {
  header("Content-Type: application/json");
  if($installation) {
    $response = createError("Not installed");
  } else {
    $apiFunction = $_GET["api"];
    if(empty($apiFunction)) {
      header("403 Forbidden");
      $response = "";
    } else if(!preg_match("/[a-zA-Z]+(\/[a-zA-Z]+)*/", $apiFunction)) {
      header("400 Bad Request");
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
          header("404 Not Found");
          $response = createError("Not found");
        } else {
          $parentClass = new ReflectionClass($parentClass);
          $apiClass = new ReflectionClass($apiClass);
          if(!$apiClass->isSubclassOf(Request::class) || !$apiClass->isInstantiable()) {
            header("400 Bad Request");
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
  }
} else {
  $requestedUri = $_GET["site"] ?? $_SERVER["REQUEST_URI"];
  if (($index = strpos($requestedUri, "?")) !== false) {
    $requestedUri = substr($requestedUri, 0, $index);
  }

  if (($index = strpos($requestedUri, "#")) !== false) {
    $requestedUri = substr($requestedUri, 0, $index);
  }

  if (startsWith($requestedUri, "/")) {
    $requestedUri = substr($requestedUri, 1);
  }

  if ($installation) {
    if ($requestedUri !== "" && $requestedUri !== "index.php") {
      $response = "Redirecting to <a href=\"/\">/</a>";
      header("Location: /");
    } else {
      $document = new Documents\Install($user);
      $response = $document->getCode();
    }
  } else {

    $req = new \Api\Routes\Find($user);
    $success = $req->execute(array("request" => $requestedUri));
    $response = "";
    if (!$success) {
      http_response_code(500);
      $response = "Unable to find route: " . $req->getLastError();
    } else {
      $route = $req->getResult()["route"];
      if (is_null($route)) {
        $response = (new Document404($user))->getCode();
      } else {
        $target = trim(explode("\n", $route["target"])[0]);
        $extra = $route["extra"] ?? "";

        $pattern = str_replace("/","\\/", $route["request"]);
        $pattern = "/$pattern/i";
        if (!startsWith($requestedUri, '/')) {
          $requestedUri = "/$requestedUri";
        }

        @preg_match("$pattern", $requestedUri, $match);
        if (is_array($match) && !empty($match)) {
          foreach($match as $index => $value) {
            $target = str_replace("$$index", $value, $target);
            $extra  = str_replace("$$index", $value, $extra);
          }
        }

        switch ($route["action"]) {
          case "redirect_temporary":
            http_response_code(307);
            header("Location: $target");
            break;
          case "redirect_permanently":
            http_response_code(308);
            header("Location: $target");
            break;
          case "static":
            $currentDir = dirname(__FILE__);
            $response = serveStatic($currentDir, $target);
            break;
          case "dynamic":
            $file = getClassPath($target);
            if (!file_exists($file) || !is_subclass_of($target, Document::class)) {
              $document = new Document404($user, $extra);
            } else {
              $document = new $target($user, $extra);
            }

            $response = $document->getCode();
            break;
        }
      }
    }

    $user->processVisit();
  }
}

$user->sendCookies();
die($response);