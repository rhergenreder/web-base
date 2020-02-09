<?php

  function clamp($val, $min, $max) {
    if($val < $min) return $min;
    if($val > $max) return $max;
    return $val;
  }

  function downloadFile($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
  }

  function getSubclassesOf($parent) {
    $result = array();
    foreach (get_declared_classes() as $class) {
        if (is_subclass_of($class, $parent))
            $result[] = $class;
    }
    return $result;
  }

  function includeDir($dir, $aIgnore = array(), $recursive = false) {
    $aIgnore[] = '.';
    $aIgnore[] = '..';
    $aFiles = array_diff(scandir($dir), $aIgnore);

    foreach($aFiles as $file) {
      $file = $dir . '/' . $file;
      if(is_dir($file)) {
        if($recursive) {
          includeDir($file, $aIgnore, true);
        }
      } else {
        require_once $file;
      }
    }
  }

  function generateRandomString($length) {
    $randomString = '';
    if($length > 0) {
      $numCharacters = 26 + 26 + 10; // a-z + A-Z + 0-9
      for ($i = 0; $i < $length; $i++)
      {
        $num = random_int(0, $numCharacters - 1);
        if($num < 26) $randomString .= chr(ord('a') + $num);
        else if($num - 26 < 26) $randomString .= chr(ord('A') + $num - 26);
        else $randomString .= chr(ord('0') + $num - 26 - 26);
      }
    }

    return $randomString;
  }

  function cleanPath($path) {
    if($path === '')
      return $path;

    $path = str_replace('\\', '/', $path);
    $path = str_replace('/./', '/', $path);

    if($path[0] !== '/')
      $path = '/' . $path;

    $path = str_replace('/../', '/', $path);
    return $path;
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

  function isCalledDirectly($file) {
    return $_SERVER['SCRIPT_FILENAME'] === $file;
  }

  function anonymzeEmail($mail) {
    if(($pos = strpos($mail, '@')) !== -1) {
      $name = substr($mail, 0, $pos);
      $host = substr($mail, $pos + 1);
      if(strlen($name) > 2) $mail = substr($name, 0, 2) . str_repeat('*', strlen($name) - 2) . "@$host";
      else $mail = $mail = str_repeat('*', strlen($name)) . "@$host";
    }

    return $mail;
  }

  function intendCode($code, $escape=true) {
    $newCode = "";
    $first = true;
    $brackets = array();
    $intend = 0;

    foreach(explode("\n", $code) as $line) {
      if(!$first) $newCode .= "\n";
      if($escape) $line = htmlspecialchars($line);
      $line = trim($line);

      if(count($brackets) > 0 && startsWith($line, current($brackets))) {
        $intend = max(0, $intend - 2);
        array_pop($brackets);
      }

      $newCode .= str_repeat(" ", $intend);
      $newCode .= $line;
      $first = false;

      if(endsWith($line, "{")) {
        $intend += 2;
        array_push($brackets, "}");
      } else if(endsWith($line, "(")) {
        $intend += 2;
        array_push($brackets, ")");
      }
    }

    return $newCode;
  }

  function replaceCssSelector($sel) {
    return preg_replace("~[.#<>]~", "_", preg_replace("~[:\-]~", "", $sel));
  }
?>
