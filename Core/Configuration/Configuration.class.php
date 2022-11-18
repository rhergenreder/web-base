<?php

namespace Core\Configuration;

use Core\Objects\ConnectionData;

class Configuration {

  const className = "\Site\Configuration\Database";
  private ?ConnectionData $database;
  private Settings $settings;

  function __construct() {
    $this->database = null;
    $this->settings = Settings::loadDefaults();

    $className = self::className;
    $path = getClassPath($className, ".class");
    if (file_exists($path) && is_readable($path)) {
      include_once $path;
      if (class_exists($className)) {
        $this->database = new $className();
      }
    }
  }

  public function getDatabase(): ?ConnectionData {
    return $this->database;
  }

  public function getSettings(): Settings {
    return $this->settings;
  }

  public static function create(string $className, $data) {
    $path = getClassPath($className);
    $classNameShort = explode("\\", $className);
    $classNameShort = end($classNameShort);

    if ($data) {
      if (is_string($data)) {
        $key = var_export($data, true);
        $code = intendCode(
          "<?php

          namespace Core\Configuration;

          class $classNameShort extends KeyData {
          
            public function __construct() {
              parent::__construct($key);
            }
            
          }", false
        );
      } else if ($data instanceof ConnectionData) {
        $superClass = get_class($data);
        $host = var_export($data->getHost(), true);
        $port = var_export($data->getPort(), true);
        $login = var_export($data->getLogin(), true);
        $password = var_export($data->getPassword(), true);

        $properties = "";
        foreach ($data->getProperties() as $key => $val) {
          $key = var_export($key, true);
          $val = var_export($val, true);
          $properties .= "\n\$this->setProperty($key, $val);";
        }

        $code = intendCode(
          "<?php

          namespace Site\Configuration;

          class $classNameShort extends \\$superClass {

            public function __construct() {
              parent::__construct($host, $port, $login, $password);$properties
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

  public function setDatabase(ConnectionData $connectionData): void {
    $this->database = $connectionData;
  }
}