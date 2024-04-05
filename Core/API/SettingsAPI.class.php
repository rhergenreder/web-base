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

  use Core\API\Parameter\ArrayType;
  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\API\SettingsAPI;
  use Core\Configuration\Settings;
  use Core\Driver\SQL\Column\Column;
  use Core\Driver\SQL\Condition\CondBool;
  use Core\Driver\SQL\Condition\CondIn;
  use Core\Driver\SQL\Query\Insert;
  use Core\Driver\SQL\Strategy\UpdateStrategy;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Group;

  class Get extends SettingsAPI {

    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'key' => new StringType('key', -1, true, NULL)
      ));
    }

    public function _execute(): bool {
       $key = $this->getParam("key");
       $sql = $this->context->getSQL();

       $settings = Settings::getAll($sql, $key);
       if ($settings !== null) {
         $this->result["settings"] = $settings;
       } else {
         return $this->createError("Error fetching settings: " . $sql->getLastError());
       }

       return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN], "Allows users to fetch site settings", true);
    }
  }

  class Set extends SettingsAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'settings' => new ArrayType("settings", Parameter::TYPE_MIXED)
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

      foreach ($values as $key => $value) {
        if (!$paramKey->parseParam($key)) {
          $key = print_r($key, true);
          return $this->createError("Invalid Type for key in parameter settings: '$key' (Required: " . $paramKey->getTypeName() . ")");
        } else if (!is_null($value) && !$paramValue->parseParam($value)) {
          $value = print_r($value, true);
          return $this->createError("Invalid Type for value in parameter settings for key '$key': '$value' (Required: " . $paramValue->getTypeName() . ")");
        } else if(preg_match("/^[a-zA-Z_][a-zA-Z_0-9-]*$/", $paramKey->value) !== 1) {
          return $this->createError("The property key should only contain alphanumeric characters, underscores and dashes");
        } else {
          if (!is_null($paramValue->value)) {
            $query->addRow($paramKey->value, $paramValue->value);
          } else {
            $deleteKeys[] = $paramKey->value;
          }
          $keys[] = $paramKey->value;
          $paramKey->reset();
          $paramValue->reset();
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

      if (!empty($deleteKeys) && !$this->deleteKeys($deleteKeys)) {
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

    private function deleteKeys(array $keys): bool {
      $sql = $this->context->getSQL();
      $res = $sql->delete("Settings")
        ->where(new CondIn(new Column("name"), $keys))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    public static function getDefaultACL(Insert $insert): void {
      $insert->addRow(self::getEndpoint(), [Group::ADMIN], "Allows users to modify site settings", true);
    }
  }
}