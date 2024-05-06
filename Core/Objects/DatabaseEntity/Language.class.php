<?php

namespace Core\Objects\DatabaseEntity {

  use Core\Objects\DatabaseEntity\Attribute\MaxLength;
  use Core\Objects\DatabaseEntity\Attribute\Transient;
  use Core\Objects\DatabaseEntity\Controller\DatabaseEntity;

  class Language extends DatabaseEntity {

    const AMERICAN_ENGLISH = 1;
    const GERMAN_STANDARD = 2;

    const LANG_CODE_PATTERN = "/^[a-zA-Z]{2}_[a-zA-Z]{2}$/";
    const LANG_MODULE_PATTERN = "/[a-zA-Z0-9_-]/";

    #[MaxLength(5)] private string $code;
    #[MaxLength(32)] private string $name;

    #[Transient] protected array $entries = [];

    public function __construct(?int $id, string $code, string $name) {
      parent::__construct($id);
      $this->code = $code;
      $this->name = $name;
    }

    public function getCode(): string {
      return $this->code;
    }

    public function getShortCode(): string {
      return substr($this->code, 0, 2);
    }

    public function getName(): string {
      return $this->name;
    }

    public function sendCookie(string $domain) {
      setcookie('lang', $this->code, 0, "/", $domain, false, false);
    }

    public function jsonSerialize(?array $propertyNames = null): array {
      $jsonData = parent::jsonSerialize($propertyNames);

      if ($propertyNames === null || in_array("shortCode", $propertyNames)) {
        $jsonData["shortCode"] = explode("_", $this->code)[0];
      }

      return $jsonData;
    }

    public function activate() {
      global $LANGUAGE;
      $LANGUAGE = $this;
    }

    public static function getInstance(): Language {
      global $LANGUAGE;
      return $LANGUAGE;
    }

    public static function DEFAULT_LANGUAGE(): Language {
      return self::getPredefinedValues()[0];
    }

    public function getEntries(?string $module = null): ?array {
      if (!$module) {
        return $this->entries;
      } else {
        return $this->entries[$module] ?? null;
      }
    }

    public function translate(string $key): string {
      if (preg_match("/(\w+)\.(\w+)/", $key, $matches)) {
        $module = $matches[1];
        $moduleKey = $matches[2];
        if ($this->hasModule($module) && array_key_exists($moduleKey, $this->entries[$module])) {
          return $this->entries[$module][$moduleKey];
        }
      }

      return $key ? "[$key]" : "";
    }

    public function addModule(string $module, array $entries) {
      if ($this->hasModule($module)) {
        $this->entries[$module] = array_merge($this->entries[$module], $entries);
      } else {
        $this->entries[$module] = $entries;
      }
    }

    public function loadModule(string $module, bool $forceReload = false): bool {
      if ($this->hasModule($module) && !$forceReload) {
        return true;
      }

      if (!preg_match(self::LANG_MODULE_PATTERN, $module)) {
        return false;
      }

      if (!preg_match(self::LANG_CODE_PATTERN, $this->code)) {
        return false;
      }

      foreach (["Site", "Core"] as $baseDir) {
        $filePath = realpath(implode("/", [$baseDir, "Localization", $this->code, "$module.php"]));
        if ($filePath && is_file($filePath)) {
          $moduleEntries = @include_once $filePath;
          $this->addModule($module, $moduleEntries);
          return true;
        }
      }

      return false;
    }

    public function hasModule(string $module): bool {
      return array_key_exists($module, $this->entries);
    }

    public static function getPredefinedValues(): array {
      return [
        new Language(Language::AMERICAN_ENGLISH, "en_US", 'English (US)'),
        new Language(Language::GERMAN_STANDARD, "de_DE", 'Deutsch (Standard)'),
      ];
    }

    public static function fromHeader(): ?Language {
      if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $acceptedLanguages = explode(',', $acceptLanguage);
        foreach ($acceptedLanguages as $code) {
          if (strlen($code) == 2) {
            $code = $code . '_' . strtoupper($code);
          }

          $code = str_replace("-", "_", $code);
          if (!preg_match(self::LANG_CODE_PATTERN, $code)) {
            continue;
          }

          return new Language(NULL, $code, "");
        }
      }

      return null;
    }
  }
}

namespace {
  function L(string $key): string {
    if (!array_key_exists('LANGUAGE', $GLOBALS)) {
      return $key ? "[$key]" : "";
    }

    global $LANGUAGE;
    return $LANGUAGE->translate($key);
  }
}
