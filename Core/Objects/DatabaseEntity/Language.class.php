<?php

namespace Core\Objects\DatabaseEntity {

  use Core\Driver\SQL\SQL;
  use Core\Objects\DatabaseEntity\Attribute\MaxLength;
  use Core\Objects\DatabaseEntity\Attribute\Transient;
  use Core\Objects\lang\LanguageModule;

  // TODO: language from cookie?
  class Language extends DatabaseEntity {

    const LANG_CODE_PATTERN = "/^[a-zA-Z]{2}_[a-zA-Z]{2}$/";

    #[MaxLength(5)] private string $code;
    #[MaxLength(32)] private string $name;

    #[Transient] private array $modules = [];
    #[Transient] protected array $entries = [];

    public function __construct(int $id, string $code, string $name) {
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

    public function loadModule(LanguageModule|string $module) {
      if (!is_object($module)) {
        $module = new $module();
      }

      if (!in_array($module, $this->modules)) {
        $moduleEntries = $module->getEntries($this->code);
        $this->entries = array_merge($this->entries, $moduleEntries);
        $this->modules[] = $module;
      }
    }

    public function translate(string $key): string {
      return $this->entries[$key] ?? $key;
    }

    public function sendCookie(string $domain) {
      setcookie('lang', $this->code, 0, "/", $domain, false, false);
    }

    public function jsonSerialize(): array {
      return array(
        'id' => $this->getId(),
        'code' => $this->code,
        'shortCode' => explode("_", $this->code)[0],
        'name' => $this->name,
      );
    }

    public function activate() {
      global $LANGUAGE;
      $LANGUAGE = $this;
    }

    public static function DEFAULT_LANGUAGE(bool $fromCookie = true): Language {
      if ($fromCookie && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
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

          return new Language(0, $code, "");
        }
      }

      return new Language(1, "en_US", "American English");
    }

    public function getEntries(): array {
      return $this->entries;
    }
  }
}

namespace {
  function L($key) {
    if (!array_key_exists('LANGUAGE', $GLOBALS))
      return $key;

    global $LANGUAGE;
    return $LANGUAGE->translate($key);
  }
}
