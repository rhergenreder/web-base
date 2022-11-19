<?php

namespace Core\API {

  use Core\Objects\Context;

  abstract class SettingsAPI extends Request {
    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }
  }
}

namespace Core\API\Settings {

  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\API\SettingsAPI;
  use Core\Configuration\Settings;
  use Core\Driver\SQL\Column\Column;
  use Core\Driver\SQL\Condition\CondBool;
  use Core\Driver\SQL\Condition\CondIn;
  use Core\Driver\SQL\Condition\CondNot;
  use Core\Driver\SQL\Condition\CondRegex;
  use Core\Driver\SQL\Strategy\UpdateStrategy;
  use Core\Objects\Context;

  class Get extends SettingsAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'key' => new StringType('key', -1, true, NULL)
      ));
    }

    public function _execute(): bool {
       $key = $this->getParam("key");
       $sql = $this->context->getSQL();

       $query = $sql->select("name", "value") ->from("Settings");

       if (!is_null($key)) {
         $query->where(new CondRegex(new Column("name"), $key));
       }

       // filter sensitive values, if called from outside
       if ($this->isExternalCall()) {
         $query->where(new CondNot("private"));
       }

       $res = $query->execute();

       $this->success = ($res !== FALSE);
       $this->lastError = $sql->getLastError();

       if ($this->success) {
         $settings = array();
         foreach($res as $row) {
           $settings[$row["name"]] = $row["value"];
         }
         $this->result["settings"] = $settings;
       }

       return $this->success;
    }
  }

  class Set extends SettingsAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'settings' => new Parameter('settings', Parameter::TYPE_ARRAY)
      ));
    }

    public function _execute(): bool {
      $values = $this->getParam("settings");
      if (empty($values)) {
        return $this->createError("No values given.");
      }

      $paramKey = new StringType('key', 32);
      $paramValue = new StringType('value', 1024, true, NULL);

      $sql = $this->context->getSQL();
      $query = $sql->insert("Settings", array("name", "value"));
      $keys = array();
      $deleteKeys = array();

      foreach($values as $key => $value) {
        if (!$paramKey->parseParam($key)) {
          $key = print_r($key, true);
          return $this->createError("Invalid Type for key in parameter settings: '$key' (Required: " . $paramKey->getTypeName() . ")");
        } else if(!is_null($value) && !$paramValue->parseParam($value)) {
          $value = print_r($value, true);
          return $this->createError("Invalid Type for value in parameter settings: '$value' (Required: " . $paramValue->getTypeName() . ")");
        } else if(preg_match("/^[a-zA-Z_][a-zA-Z_0-9-]*$/", $paramKey->value) !== 1) {
          return $this->createError("The property key should only contain alphanumeric characters, underscores and dashes");
        } else {
          if (!is_null($paramValue->value)) {
            $query->addRow($paramKey->value, $paramValue->value);
          } else {
            $deleteKeys[] = $paramKey->value;
          }
          $keys[] = $paramKey->value;
        }
      }

      if ($this->isExternalCall()) {
        $column = $this->checkReadonly($keys);
        if(!$this->success) {
          return false;
        } else if($column !== null) {
          return $this->createError("Column '$column' is readonly.");
        }
      }

      if (!empty($deleteKeys) && !$this->deleteKeys($keys)) {
        return false;
      }

      if (count($deleteKeys) !== count($keys)) {
        $query->onDuplicateKeyStrategy(new UpdateStrategy(
          array("name"),
          array("value" => new Column("value")))
        );


        $this->success = ($query->execute() !== FALSE);
        $this->lastError = $sql->getLastError();
      }

      return $this->success;
    }

    private function checkReadonly(array $keys) {
      $sql = $this->context->getSQL();
      $res = $sql->select("name")
        ->from("Settings")
        ->where(new CondBool("readonly"))
        ->where(new CondIn(new Column("name"), $keys))
        ->first()
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success && $res !== null) {
        return $res["name"];
      }

      return null;
    }

    private function deleteKeys(array $keys) {
      $sql = $this->context->getSQL();
      $res = $sql->delete("Settings")
        ->where(new CondIn(new Column("name"), $keys))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }

  class GenerateJWT extends SettingsAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "type" => new StringType("type", 32, true, "HS512")
      ]);
    }

    protected function _execute(): bool {
      $algorithm = $this->getParam("type");
      if (!Settings::isJwtAlgorithmSupported($algorithm)) {
        return $this->createError("Algorithm is not supported");
      }

      $settings = $this->context->getSettings();
      if (!$settings->generateJwtKey($algorithm)) {
        return $this->createError("Error generating JWT-Key: " . $settings->getLogger()->getLastMessage());
      }

      $saveRequest = $settings->saveJwtKey($this->context);
      if (!$saveRequest->success()) {
        return $this->createError("Error saving JWT-Key: " . $saveRequest->getLastError());
      }

      $this->result["jwt_public_key"] = $settings->getJwtPublicKey(false)?->getKeyMaterial();
      return true;
    }
  }
}