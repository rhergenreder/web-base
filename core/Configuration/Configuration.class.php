<?php

namespace Configuration;

class Configuration {

  private $database;
  private $mail;
  private $jwt;
  private $google;

  function __construct() {
  }

  public function load() {
    try {

      $classes = array(
        \Configuration\Database::class => &$this->database,
        \Configuration\Mail::class => &$this->mail,
        \Configuration\JWT::class => &$this->jwt,
        \Configuration\Google::class => &$this->google,
      );

      $success = true;
      foreach($classes as $class => &$ref) {
        $path = getClassPath($class);
        if(!file_exists($path)) {
          $success = false;
        } else {
          include_once $path;
          if(class_exists($class)) {
            $ref = new $class();
          }
        }
      }

      return $success;
    } catch(\Error $e) {
      die($e);
    }
  }

  public function getDatabase() { return $this->database; }
  public function getJWT() { return $this->jwt; }
  public function getMail() { return $this->mail; }

  public function isFilePresent($className) {
    $path = getClassPath("\\Configuration\\$className");
    return file_exists($path);
  }

  public function create($className, $data) {
    $path = getClassPath("\\Configuration\\$className");

    if($data) {

      // TODO: Generalize this...
      $superClass = get_class($data);
      $host = addslashes($data->getHost());
      $port = intval($data->getPort());
      $login = addslashes($data->getLogin());
      $password = addslashes($data->getPassword());

      $properties = "";
      foreach($data->getProperties() as $key => $val) {
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
        }

        ?>", false
      );
    } else {
      $code = intendCode(
        "<?php

        ?>", false);
    }

    return file_put_contents($path, $code);
  }

  public function delete($className) {
    $path = getClassPath("\\Configuration\\$className");
    if(file_exists($path)) {
      return unlink($path);
    }

    return true;
  }
};

?>
