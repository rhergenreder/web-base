<?php

namespace Core\API {

  use Core\API\Parameter\IntegerType;
  use Core\API\Parameter\StringType;
  use Core\Objects\Captcha\CaptchaProvider;
  use Core\Objects\Context;
  use Core\API\Parameter\ArrayType;
  use Core\API\Parameter\Parameter;

  abstract class SettingsAPI extends Request {

    protected array $predefinedKeys;

    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);

      // API parameters should be more configurable, e.g. allow regexes, min/max values for numbers, etc.
      $this->predefinedKeys = [
        "allowed_extensions" => new ArrayType("allowed_extensions", Parameter::TYPE_STRING),
        "trusted_domains" => new ArrayType("trusted_domains", Parameter::TYPE_STRING),
        "user_registration_enabled" => new Parameter("user_registration_enabled", Parameter::TYPE_BOOLEAN),
        "captcha_provider" => new StringType("captcha_provider", -1, true, "disabled", CaptchaProvider::PROVIDERS),
        "mail_enabled" => new Parameter("mail_enabled", Parameter::TYPE_BOOLEAN),
        "mail_port" => new IntegerType("mail_port", 1, 65535),
        "rate_limiting_enabled" => new Parameter("rate_limiting_enabled", Parameter::TYPE_BOOLEAN),
        "redis_port" => new IntegerType("redis_port", 1, 65535),
      ];
    }
  }
}

namespace Core\API\Settings {

  use Core\API\Parameter\ArrayType;
  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\RegexType;
  use Core\API\Parameter\StringType;
  use Core\API\SettingsAPI;
  use Core\Configuration\Settings;
  use Core\Driver\SQL\Column\Column;
  use Core\Driver\SQL\Condition\CondBool;
  use Core\Driver\SQL\Condition\CondIn;
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

       $settings = Settings::getAll($sql, $key, $this->isExternalCall());
       if ($settings !== null) {
         $this->result["settings"] = $settings;
       } else {
         return $this->createError("Error fetching settings: " . $sql->getLastError());
       }

       return $this->success;
    }

    public static function getDescription(): string {
      return "Allows users to fetch site settings";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN];
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

      $paramKey = new RegexType('key', "[a-zA-Z_][a-zA-Z_0-9-]*");
      $paramValueDefault = new StringType('value', 1024, true, NULL);

      $sql = $this->context->getSQL();
      $query = $sql->insert("Settings", ["name", "value"]);
      $keys = array();
      $deleteKeys = array();

      foreach ($values as $key => $value) {
        $paramValue = $this->predefinedKeys[$key] ?? $paramValueDefault;

        if (!$paramKey->parseParam($key)) {
          $key = print_r($key, true);
          return $this->createError("Invalid Type for key in parameter settings: '$key' (Required: " . $paramKey->getTypeName() . ")");
        } else if (!is_null($value) && !$paramValue->parseParam($value)) {
          $value = print_r($value, true);
          return $this->createError("Invalid Type for value in parameter settings for key '$key': '$value' (Required: " . $paramValue->getTypeName() . ")");
        } else {
          if (!is_null($paramValue->value)) {
            $query->addRow($paramKey->value, json_encode($paramValue->value));
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
          ["name"],
          ["value" => new Column("value")])
        );


        $this->success = ($query->execute() !== FALSE);
        $this->lastError = $sql->getLastError();

        if ($this->success) {
          $this->logger->info("The site settings were changed");
        }
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

    public static function getDescription(): string {
      return "Allows users to modify site settings";
    }

    public static function getDefaultPermittedGroups(): array {
      return [Group::ADMIN];
    }
  }
}