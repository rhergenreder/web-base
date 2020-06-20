<?php

use Api\Request;
use Configuration\Configuration;
use Documents\Document404;
use Elements\Document;

include_once 'core/core.php';
include_once 'core/datetime.php';
include_once 'core/constants.php';

if (!is_readable(getClassPath(Configuration::class))) {
  header("Content-Type: application/json");
  die(json_encode(array( "success" => false, "msg" => "Configuration directory is not readable, check permissions before proceeding." )));
}

spl_autoload_extensions(".php");
spl_autoload_register(function($class) {
  $full_path = getClassPath($class, true);
  if(file_exists($full_path)) {
    include_once $full_path;
  } else {
    include_once getClassPath($class, false);
  }
});

$config = new Configuration();
$installation = (!$config->load());
$user   = new Objects\User($config);

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
        if(!file_exists($file)) {
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
  $documentName = $_GET["site"] ?? "/";
  if ($installation) {
    if ($documentName !== "" && $documentName !== "index.php") {
      $response = "Redirecting to <a href=\"/\">/</a>";
      header("Location: /");
    } else {
      $document = new Documents\Install($user);
      $response = $document->getCode();
    }
  } else {

    $req = new \Api\Routes\Find($user);
    $success = $req->execute(array("request" => $documentName));
    $response = "";
    if (!$success) {
      $response = "Unable to find route: " . $req->getLastError();
    } else {
      $route = $req->getResult()["route"];
      if (is_null($route)) {
        $response = (new Document404($user))->getCode();
      } else {
        $target = trim(explode("\n", $route["target"])[0]);
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
            http_response_code(501);
            $response = "Not implemented yet.";
            break;
          case "dynamic":
            $view = $route["extra"] ?? "";
            $file = getClassPath($target);
            if(!file_exists($file) || !is_subclass_of($target, Document::class)) {
              $document = new Document404($user, $view);
            } else {
              $document = new $target($user, $view);
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