<?php

function getClassPath($class, $suffix=true) {
  $path = str_replace('\\', '/', $class);
  $suffix = ($suffix ? ".class" : "");
  return "core/$path$suffix.php";
}

function createError($msg) {
  return json_encode(array("success" => false, "msg" => $msg));
}

spl_autoload_extensions(".php");
spl_autoload_register(function($class) {
  $full_path = getClassPath($class);
  if(file_exists($full_path))
    include_once $full_path;
  else
    include_once getClassPath($class, false);
});

include_once 'core/core.php';
include_once 'core/datetime.php';
include_once 'core/constants.php';

$config = new Configuration\Configuration();
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
      $apiFunction = implode("\\", array_map('ucfirst', explode("/", $apiFunction)));
      if($apiFunction[0] !== "\\") $apiFunction = "\\$apiFunction";
      $class = "\\Api$apiFunction";
      $file = getClassPath($class);
      if(!file_exists($file)) {
        header("404 Not Found");
        $response = createError("Not found");
      } else if(!is_subclass_of($class, \Api\Request::class)) {
        header("400 Bad Request");
        $response = createError("Inalid Method");
      } else {
        $request = new $class($user, true);
        $success = $request->execute();
        $msg = $request->getLastError();
        $response = $request->getJsonResult();
      }
    }
  }
} else {
  $documentName = $_GET["site"];
  if ($installation) {
    if ($documentName !== "" && $documentName !== "index.php") {
      $response = "Redirecting to <a href=\"/\">/</a>";
      header("Location: /");
    } else {
      $document = new Documents\Install($user);
      $response = $document->getCode();
    }
  } else {
    if(empty($documentName) || strcasecmp($documentName, "install") === 0) {
      $documentName = "home";
    } else if(!preg_match("/[a-zA-Z]+(\/[a-zA-Z]+)*/", $documentName)) {
      $documentName = "Document404";
    }

    $documentName = strtoupper($documentName[0]) . substr($documentName, 1);
    $documentName = str_replace("/", "\\", $documentName);
    $class = "\\Documents\\$documentName";
    $file = getClassPath($class);
    if(!file_exists($file) || !is_subclass_of($class, \Elements\Document::class)) {
      $document = new \Documents\Document404($user);
    } else {
      $document = new $class($user);
    }

    $response = $document->getCode();
  }
}

$user->sendCookies();
die($response);
?>
