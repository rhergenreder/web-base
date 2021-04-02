<?php

namespace Api {

  abstract class SettingsAPI extends Request {

  }

}

namespace Api\Settings {

  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\SettingsAPI;
  use Driver\SQL\Column\Column;
  use Driver\SQL\Condition\CondBool;
  use Driver\SQL\Condition\CondIn;
  use Driver\SQL\Condition\CondNot;
  use Driver\SQL\Condition\CondRegex;
  use Driver\SQL\Strategy\UpdateStrategy;
  use Objects\User;

  class Get extends SettingsAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'key' => new StringType('key', -1, true, NULL)
      ));
    }

    public function execute($values = array()): bool {
       if(!parent::execute($values)) {
         return false;
       }

       $key = $this->getParam("key");
       $sql = $this->user->getSQL();

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
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'settings' => new Parameter('settings', Parameter::TYPE_ARRAY)
      ));
    }

    public function execute($values = array()): bool {
      if (!parent::execute($values)) {
        return false;
      }

      $values = $this->getParam("settings");
      if (empty($values)) {
        return $this->createError("No values given.");
      }

      $paramKey = new StringType('key', 32);
      $paramValue = new StringType('value', 1024, true, NULL);

      $sql = $this->user->getSQL();
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
      $sql = $this->user->getSQL();
      $res = $sql->select("name")
        ->from("Settings")
        ->where(new CondBool("readonly"))
        ->where(new CondIn("name", $keys))
        ->limit(1)
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success && !empty($res)) {
        return $res[0]["name"];
      }

      return null;
    }

    private function deleteKeys(array $keys) {
      $sql = $this->user->getSQL();
      $res = $sql->delete("Settings")
        ->where(new CondIn("name", $keys))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }
}