<?php

define("WEBBASE_VERSION", "0.1.0-alpha");

function getProtocol() {
  return stripos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https' : 'http';
}

function generateRandomString($length): string {
  $randomString = '';
  if ($length > 0) {
    $numCharacters = 26 + 26 + 10; // a-z + A-Z + 0-9
    for ($i = 0; $i < $length; $i++) {
      try {
        $num = random_int(0, $numCharacters - 1);
      } catch (Exception $e) {
        $num = rand(0, $numCharacters - 1);
      }

      if ($num < 26) $randomString .= chr(ord('a') + $num);
      else if ($num - 26 < 26) $randomString .= chr(ord('A') + $num - 26);
      else $randomString .= chr(ord('0') + $num - 26 - 26);
    }
  }

  return $randomString;
}

function startsWith($haystack, $needle) {
  $length = strlen($needle);
  return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle) {
  $length = strlen($needle);
  if ($length == 0)
    return true;

  return (substr($haystack, -$length) === $needle);
}

function intendCode($code, $escape = true) {
  $newCode = "";
  $first = true;
  $brackets = array();
  $intend = 0;

  foreach (explode("\n", $code) as $line) {
    if (!$first) $newCode .= "\n";
    if ($escape) $line = htmlspecialchars($line);
    $line = trim($line);

    if (count($brackets) > 0 && startsWith($line, current($brackets))) {
      $intend = max(0, $intend - 2);
      array_pop($brackets);
    }

    $newCode .= str_repeat(" ", $intend);
    $newCode .= $line;
    $first = false;

    if (endsWith($line, "{")) {
      $intend += 2;
      array_push($brackets, "}");
    } else if (endsWith($line, "(")) {
      $intend += 2;
      array_push($brackets, ")");
    }
  }

  return $newCode;
}

function replaceCssSelector($sel) {
  return preg_replace("~[.#<>]~", "_", preg_replace("~[:\-]~", "", $sel));
}

function getClassPath($class, $suffix = true) {
  $path = str_replace('\\', '/', $class);
  $path = array_values(array_filter(explode("/", $path)));

  if (strcasecmp($path[0], "api") === 0 && count($path) > 2 && strcasecmp($path[1], "Parameter") !== 0) {
    $path = "Api/" . $path[1] . "API";
  } else {
    $path = implode("/", $path);
  }

  $suffix = ($suffix ? ".class" : "");
  return "core/$path$suffix.php";
}

function createError($msg) {
  return json_encode(array("success" => false, "msg" => $msg));
}

function serveStatic(string $webRoot, string $file): string {

  $path = realpath($webRoot . "/" . $file);
  if (!startsWith($path, $webRoot . "/")) {
    http_response_code(406);
    return "<b>Access restricted, requested file outside web root:</b> " . htmlspecialchars($path);
  }

  if (!file_exists($path) || !is_file($path) || !is_readable($path)) {
    http_response_code(500);
    return "<b>Unable to read file:</b> " . htmlspecialchars($path);
  }

  $pathInfo = pathinfo($file);

  // maybe I will allow more later…
  $allowedExtension = array("html", "htm");
  $ext = $pathInfo["extension"] ?? "";
  if (!in_array($ext, $allowedExtension)) {
    http_response_code(406);
    return "<b>Access restricted:</b> Extension '" . htmlspecialchars($ext) . "' not allowed.";
  }

  $mimeType = mime_content_type($file);
  header("Content-Type: $mimeType");
  return readfile($file);
}
