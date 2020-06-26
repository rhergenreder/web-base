<?php

namespace Api {

  class SettingsAPI extends Request {

  }

}

namespace Api\Settings {

  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\SettingsAPI;
  use Driver\SQL\Column\Column;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondLike;
  use Driver\SQL\Condition\CondNot;
  use Driver\SQL\Condition\CondRegex;
  use Driver\SQL\Strategy\UpdateStrategy;
  use Objects\User;

  class Get extends SettingsAPI {

    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'key' => new StringType('key', 32, true, NULL)
      ));

      $this->requiredGroup = array(USER_GROUP_ADMIN);
      $this->loginRequired = true;
    }

    public function execute($values = array()) {
       if(!parent::execute($values)) {
         return false;
       }

       $key = $this->getParam("key");
       $sql = $this->user->getSQL();

       $query = $sql->select("name", "value") ->from("Settings");

       if (!is_null($key) && !empty($key)) {
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

      $this->requiredGroup = array(USER_GROUP_ADMIN);
      $this->loginRequired = true;
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $values = $this->getParam("settings");
      if (empty($values)) {
        return $this->createError("No values given.");
      }

      $paramKey = new StringType('key', 32);
      $paramValue = new StringType('value', 1024);

      $sql = $this->user->getSQL();
      $query = $sql->insert("Settings", array("name", "value"));

      foreach($values as $key => $value) {
        if (!$paramKey->parseParam($key)) {
          $key = print_r($key, true);
          return $this->createError("Invalid Type for key in parameter settings: '$key' (Required: " . $paramKey->getTypeName() . ")");
        } else if(!$paramValue->parseParam($value)) {
          $value = print_r($value, true);
          return $this->createError("Invalid Type for value in parameter settings: '$value' (Required: " . $paramValue->getTypeName() . ")");
        } else {
          $query->addRow($paramKey->value, $paramValue->value);
        }
      }

      $query->onDuplicateKeyStrategy(new UpdateStrategy(
        array("name"),
        array("value" => new Column("value")))
      );

      $this->success = ($query->execute() !== FALSE);
      $this->lastError = $sql->getLastError();
      return $this->success;
    }
  }
}