<?php

$autoLoad = implode(DIRECTORY_SEPARATOR, [__DIR__, "External", "vendor", "autoload.php"]);
if (is_file($autoLoad)) {
  require_once $autoLoad;
}

define("WEBBASE_VERSION", "1.5.1");

spl_autoload_extensions(".php");
spl_autoload_register(function($class) {
  if (!class_exists($class)) {
    $suffixes = ["", ".class", ".trait"];
    foreach ($suffixes as $suffix) {
      $full_path = WEBROOT . "/" . getClassPath($class, $suffix);
      if (file_exists($full_path)) {
        include_once $full_path;
        return;
      }
    }

    throw new Exception("Class or Trait not found: $class");
  }
});

function is_cli(): bool {
  return php_sapi_name() === "cli";
}

function getProtocol(): string {
  $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ||
              (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ||
              (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on');

  return $isSecure ? 'https' : 'http';
}

function uuidv4(): string {
  $data = random_bytes(16);
  $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
  $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function generateRandomString($length, $type = "ascii"): string {
  $randomString = '';

  $lowercase = "abcdefghijklmnopqrstuvwxyz";
  $uppercase = strtoupper($lowercase);
  $digits    = "0123456789";
  $hex       = $digits . substr($lowercase, 0, 6);
  $ascii     = $uppercase . $lowercase . $digits;

  if ($length > 0) {
    $type = strtolower($type);
    if ($type === "hex") {
      $charset = $hex;
    } else if ($type === "base64") {
      $charset = $ascii . "/+";
    } else if ($type === "base58") {
      $charset = preg_replace("/[0Oo1Il]/", "", $ascii);
    } else if ($type === "base32") {
      $charset = $uppercase . substr($digits, 2, 6);
    } else {
      $charset = $ascii;
    }

    $numCharacters = $type === "raw" ? 256 : strlen($charset);
    for ($i = 0; $i < $length; $i++) {
      try {
        $num = random_int(0, $numCharacters - 1);
      } catch (Exception $e) {
        $num = rand(0, $numCharacters - 1);
      }

      $randomString .= $type === "raw" ? chr($num) : $charset[$num];
    }
  }

  return $randomString;
}

function base64url_decode($data) {
  $base64 = strtr($data, '-_', '+/');
  return base64_decode($base64);
}

function startsWith($haystack, $needle, bool $ignoreCase = false): bool {

  $length = strlen($needle);
  if ($length === 0) {
    return true;
  }

  if ($ignoreCase) {
    $haystack = strtolower($haystack);
    $needle = strtolower($needle);
  }

  // PHP 8.0 support
  if (function_exists("str_starts_with")) {
    return str_starts_with($haystack, $needle);
  } else {
    return (substr($haystack, 0, $length) === $needle);
  }
}

function startsWithAny($haystack, array $needles, bool $ignoreCase = false): bool {
  foreach ($needles as $needle) {
    if (startsWith($haystack, $needle, $ignoreCase)) {
      return true;
    }
  }
  return false;
}

function endsWith($haystack, $needle, bool $ignoreCase = false): bool {

  $length = strlen($needle);
  if ($length === 0) {
    return true;
  }

  if ($ignoreCase) {
    $haystack = strtolower($haystack);
    $needle = strtolower($needle);
  }

  // PHP 8.0 support
  if (function_exists("str_ends_with")) {
    return str_ends_with($haystack, $needle);
  } else {
    return (substr($haystack, -$length) === $needle);
  }
}



function contains($haystack, $needle, bool $ignoreCase = false): bool {

  if (is_array($haystack)) {
    return in_array($needle, $haystack);
  }

  if ($ignoreCase) {
    $haystack = strtolower($haystack);
    $needle = strtolower($needle);
  }

  // PHP 8.0 support
  if (function_exists("str_contains")) {
    return str_contains($haystack, $needle);
  } else {
    return strpos($haystack, $needle) !== false;
  }
}

function intendCode($code, $escape = true): string {
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
      $brackets[] = "}";
    } else if (endsWith($line, "(")) {
      $intend += 2;
      $brackets[] = ")";
    }
  }

  return $newCode;
}

function replaceCssSelector($sel) {
  return preg_replace("~[.#<>]~", "_", preg_replace("~[:\-]~", "", $sel));
}

function urlId($str) {
  return urlencode(htmlspecialchars(preg_replace("[: ]","-", $str)));
}

function html_attributes(array $attributes): string {
  return implode(" ", array_map(function ($key) use ($attributes) {
    $value = htmlspecialchars($attributes[$key]);
    return "$key=\"$value\"";
  }, array_keys($attributes)));
}

function getClassPath($class, string $suffix = ".class"): string {
  $path = str_replace('\\', '/', $class);
  $path = array_values(array_filter(explode("/", $path)));

  if (count($path) > 2 && strcasecmp($path[0], "api") === 0 && strcasecmp($path[1], "Parameter") !== 0) {
    $path = "Api/" . $path[1] . "API";
  } else {
    $path = implode("/", $path);
  }

  return "core/$path$suffix.php";
}

function getClassName($class, bool $short = true): string {
  $reflection = new \ReflectionClass($class);
  if ($short) {
    return $reflection->getShortName();
  } else {
    return $reflection->getName();
  }
}

function createError($msg) {
  return json_encode(array("success" => false, "msg" => $msg));
}

function downloadFile($handle, $offset = 0, $length = null): bool {
  if($handle === false) {
    return false;
  }

  if ($offset > 0) {
    fseek($handle, $offset);
  }

  $bytesRead = 0;
  $bufferSize = 1024*16;
  while (!feof($handle) && ($length === null || $bytesRead < $length)) {
    $chunkSize = ($length === null ? $bufferSize : min($length - $bytesRead, $bufferSize));
    echo fread($handle, $chunkSize);
  }

  fclose($handle);
  return true;
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

  $pathInfo = pathinfo($path);

  // TODO: add more file extensions here, probably add them to settings?
  $allowedExtension = array("html", "htm", "pdf");
  $ext = $pathInfo["extension"] ?? "";
  if (!in_array($ext, $allowedExtension)) {
    http_response_code(406);
    return "<b>Access restricted:</b> Extension '" . htmlspecialchars($ext) . "' not allowed.";
  }

  $size = filesize($path);
  $mimeType = mime_content_type($path);
  header("Content-Type: $mimeType"); // TODO: do we need to check mime type?
  header("Content-Length: $size");
  header('Accept-Ranges: bytes');

  if (strcasecmp($_SERVER["REQUEST_METHOD"], "HEAD") !== 0) {
    $handle = fopen($path, "rb");
    if($handle === false) {
      http_response_code(500);
      return "<b>Unable to read file:</b> " . htmlspecialchars($path);
    }

    $offset = 0;
    $length = $size;

    if (isset($_SERVER['HTTP_RANGE'])) {
      preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches);
      $offset = intval($matches[1]);
      $length = intval($matches[2]) - $offset;
      http_response_code(206);
      header('Content-Range: bytes ' . $offset . '-' . ($offset + $length) . '/' . $size);
    }

    downloadFile($handle, $offset, $length);
  }

  return "";
}

function parseClass($class): string {
  if (!startsWith($class, "\\")) {
    $class = "\\$class";
  }

  $parts = explode("\\", $class);
  $parts = array_map('ucfirst', $parts);
  return implode("\\", $parts);
}
