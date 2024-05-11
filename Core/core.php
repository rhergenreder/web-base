<?php

/**
 * This file contains functions used globally without a namespace and should not require
 * any other files. It also loads the composer vendor libraries.
 */

$autoLoad = implode(DIRECTORY_SEPARATOR, [__DIR__, "External", "vendor", "autoload.php"]);
if (is_file($autoLoad)) {
  require_once $autoLoad;
}

const WEBBASE_VERSION = "2.4.1";

spl_autoload_extensions(".php");
spl_autoload_register(function ($class) {
  if (!class_exists($class)) {
    $suffixes = ["", ".class", ".trait", ".interface"];
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

function getProtocol(): string {
  $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on');

  return $isSecure ? 'https' : 'http';
}

function getCurrentHostName(): string {
  $hostname = $_SERVER["SERVER_NAME"] ?? null;
  if (empty($hostname)) {
    $hostname = $_SERVER["HTTP_HOST"] ?? null;
    if (empty($hostname)) {
      $hostname = gethostname();
    }
  }

  return $hostname;
}

function uuidv4(): string {
  $data = random_bytes(16);
  $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
  $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function generateRandomString(int $length, $type = "ascii"): string {
  $randomString = '';

  $lowercase = "abcdefghijklmnopqrstuvwxyz";
  $uppercase = strtoupper($lowercase);
  $digits = "0123456789";
  $hex = $digits . substr($lowercase, 0, 6);
  $ascii = $uppercase . $lowercase . $digits;

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

function base64url_decode($data): bool|string {
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

function html_attributes(array $attributes): string {
  return implode(" ", array_map(function ($key) use ($attributes) {
    $value = is_array($attributes[$key]) ? implode(" ", $attributes[$key]) : $attributes[$key];
    $value = htmlspecialchars($value);
    return "$key=\"$value\"";
  }, array_keys($attributes)));
}

function html_tag_short(string $tag, array $attributes = []): string {
  return html_tag_ex($tag, $attributes, "", true, true);
}

function html_tag(string $tag, array $attributes = [], $content = "", bool $escapeContent = true): string {
  return html_tag_ex($tag, $attributes, $content, $escapeContent, false);
}

function html_tag_ex(string $tag, array $attributes, $content = "", bool $escapeContent = true, bool $short = false): string {
  $attrs = html_attributes($attributes);
  if (!empty($attrs)) {
    $attrs = " " . $attrs;
  }

  if (is_array($content)) {
    $content = implode("", $content);
  }

  if ($escapeContent) {
    $content = htmlspecialchars($content);
  }

  return ($short && !empty($content)) ? "<$tag$attrs/>" : "<$tag$attrs>$content</$tag>";
}

function getClassPath($class, string $suffix = ".class"): string {
  $path = str_replace('\\', '/', $class);
  $pathParts = array_values(array_filter(explode("/", $path)));
  $pathCount = count($pathParts);

  if ($pathCount >= 3) {
    if (strcasecmp($pathParts[$pathCount - 3], "API") === 0) {
      $group = $pathParts[$pathCount - 2];
      if (strcasecmp($group, "Parameter") !== 0 && strcasecmp($group, "Traits") !== 0) {
        $pathParts = array_slice($pathParts, 0, $pathCount - 2);
        $pathParts[] = "{$group}API";
      }
    }
  }

  $path = implode("/", $pathParts);
  return "$path$suffix.php";
}

function getClassName($class, bool $short = true): string {
  $reflection = new \ReflectionClass($class);
  if ($short) {
    return $reflection->getShortName();
  } else {
    return $reflection->getName();
  }
}

function isDocker(): bool {
  return file_exists("/.dockerenv");
}

function createError($msg): array {
  return ["success" => false, "msg" => $msg];
}

function downloadFile($handle, $offset = 0, $length = null): bool {
  if ($handle === false) {
    return false;
  }

  if ($offset > 0) {
    fseek($handle, $offset);
  }

  $bytesRead = 0;
  $bufferSize = 1024 * 16;
  while (!feof($handle) && ($length === null || $bytesRead < $length)) {
    $chunkSize = ($length === null ? $bufferSize : min($length - $bytesRead, $bufferSize));
    echo fread($handle, $chunkSize);
  }

  fclose($handle);
  return true;
}

function parseClass($class): string {
  if (!startsWith($class, "\\")) {
    $class = "\\$class";
  }

  $parts = explode("\\", $class);
  $parts = array_map('ucfirst', $parts);
  return implode("\\", $parts);
}

function isClass(string $str): bool {
  $path = getClassPath($str);
  return is_file($path) && class_exists($str);
}

function getCurrentUsername(): string {
  if (function_exists("posix_getpwuid") && function_exists("posix_geteuid")) {
    return posix_getpwuid(posix_geteuid())['name'];
  }

  return exec('whoami') ?? "Unknown";
}

function rrmdir(string $dir): void {
  if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
      if ($object !== "." && $object !== "..") {
        $path = $dir . DIRECTORY_SEPARATOR . $object;
        if (is_dir($path) && !is_link($path)) {
          rrmdir($path);
        } else {
          unlink($path);
        }
      }
    }
    rmdir($dir);
  }
}

function loadEnv(?string $file = NULL, bool $putEnv = false): array|null {
  if ($file === NULL) {
    $file = WEBROOT . DIRECTORY_SEPARATOR . ".env";
  }

  if (!is_file($file)) {
    return null;
  }

  $env = parse_ini_file('.env');
  if ($putEnv) {
    foreach ($env as $key => $value) {
      putenv("$key=$value");
    }
  }

  return $env;
}