<?php

function getClassPath($class, $suffix=true) {
  $path = str_replace('\\', '/', $class);
  $suffix = ($suffix ? ".class" : "");
  return "core/$path$suffix.php";
}

function getWebRoot() {
  return dirname(__FILE__);
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
      $response = createError("Invalid Method");
    } else {
      $apiFunction = strtoupper($apiFunction[0]) . substr($apiFunction, 1);
      $class = "\\Api\\$apiFunction";
      $file = getClassPath($class);
      if(!file_exists($file)) {
        header("404 Not Found");
        $response = createError("Not found");
      } else {
        $request = new $class($user, true);
        $success = $request->execute();
        $msg = $request->getLastError();
        $response = $request->getJsonResult();
      }
    }
  }
} else {
  if ($installation) {
    $document = new Documents\Install($user);
  } else {
    $document = new Documents\Admin($user);
  }

  $response = $document->getCode();
}

$user->sendCookies();
die($response);
?>
