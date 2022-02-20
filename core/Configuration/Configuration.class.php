<?php

namespace Configuration;

use Objects\ConnectionData;

class Configuration {

  private ?ConnectionData $database;
  private Settings $settings;

  function __construct() {
    $this->database = null;
    $this->settings = Settings::loadDefaults();

    $class = \Configuration\Database::class;
    $path = getClassPath($class, ".class");
    if (file_exists($path) && is_readable($path)) {
      include_once $path;
      if (class_exists($class)) {
        $this->database = new \Configuration\Database();
      }
    }
  }

  public function getDatabase(): ?ConnectionData {
    return $this->database;
  }

  public function getSettings(): Settings {
    return $this->settings;
  }

  public function create(string $className, $data) {
    $path = getClassPath("\\Configuration\\$className");

    if ($data) {
      if (is_string($data)) {
        $key = addslashes($data);
        $code = intendCode(
          "<?php

          namespace Configuration;

          class $className extends KeyData {
          
            public function __construct() {
              parent::__construct('$key');
            }
            
          }", false
        );
      } else if ($data instanceof ConnectionData) {
        $superClass = get_class($data);
        $host = addslashes($data->getHost());
        $port = $data->getPort();
        $login = addslashes($data->getLogin());
        $password = addslashes($data->getPassword());

        $properties = "";
        foreach ($data->getProperties() as $key => $val) {
          $key = addslashes($key);
          $val = is_string($val) ? "'" . addslashes($val) . "'" : $val;
          $properties .= "\n\$this->setProperty('$key', $val);";
        }

        $code = intendCode(
          "<?php

          namespace Configuration;

          class $className extends \\$superClass {

            public function __construct() {
              parent::__construct('$host', $port, '$login', '$password');$properties
            }
          }", false
        );
      } else {
        return false;
      }
    } else {
      $code = "<?php";
    }

    return @file_put_contents($path, $code);
  }

  public function delete(string $className): bool {
    $path = getClassPath("\\Configuration\\$className");
    if (file_exists($path)) {
      return unlink($path);
    }

    return true;
  }
}