<?php

namespace Objects {

    use Objects\lang\LanguageModule;

    class Language extends ApiObject {

    const LANG_CODE_PATTERN = "/^[a-zA-Z]+_[a-zA-Z]+$/";

    private int $languageId;
    private string $langCode;
    private string $langName;
    private array $modules;

    protected array $entries;

    public function __construct($languageId, $langCode, $langName) {
      $this->languageId = $languageId;
      $this->langCode = $langCode;
      $this->langName = $langName;
      $this->entries = array();
      $this->modules = array();
    }

    public function getId() { return $this->languageId; }
    public function getCode() { return $this->langCode; }
    public function getShortCode() { return substr($this->langCode, 0, 2); }
    public function getName() { return $this->langName; }
    public function getIconPath() { return "/img/icons/lang/$this->langCode.gif"; }
    public function getEntries() { return $this->entries; }
    public function getModules() { return $this->modules; }

    public function loadModule(LanguageModule $module) {
      if(!is_object($module))
        $module = new $module;

      $aModuleEntries = $module->getEntries($this->langCode);
      $this->entries = array_merge($this->entries, $aModuleEntries);
      $this->modules[] = $module;
    }

    public function translate($key) {
      if(isset($this->entries[$key]))
        return $this->entries[$key];

      return $key;
    }

    public function sendCookie() {
      setcookie('lang', $this->langCode, 0, "/", "");
    }

    public function jsonSerialize() {
      return array(
        'uid' => $this->languageId,
        'code' => $this->langCode,
        'name' => $this->langName,
      );
    }

    public static function newInstance($languageId, $langCode, $langName) {

      if(!preg_match(Language::LANG_CODE_PATTERN, $langCode)) {
        return false;
      }

      // TODO: include dynamically wanted Language
      return new Language($languageId, $langCode, $langName);

      // $className = $langCode
      // return new $className($languageId, $langCode);
    }

    public function load() {
      global $LANGUAGE;
      $LANGUAGE = $this;
    }

    public static function DEFAULT_LANGUAGE() {
      if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $aSplit = explode(',',$acceptLanguage);
        foreach($aSplit as $code) {
          if(strlen($code) == 2) {
            $code = $code . '_' . strtoupper($code);
          }

          $code = str_replace("-", "_", $code);
          if(strlen($code) != 5)
            continue;

          $lang = Language::newInstance(0, $code, "");
          if($lang)
            return $lang;
        }
      }

      return Language::newInstance(1, "en_US", "American English");
    }
  };
}

namespace {

    function L($key) {
    if(!array_key_exists('LANGUAGE', $GLOBALS))
      return $key;

    global $LANGUAGE;
    return $LANGUAGE->translate($key);
  }

  function LANG_NAME() {
    if(!array_key_exists('LANGUAGE', $GLOBALS))
      return "LANG_NAME";

    global $LANGUAGE;
    return $LANGUAGE->getName();
  }

  function LANG_CODE() {
    if(!array_key_exists('LANGUAGE', $GLOBALS))
      return "LANG_CODE";

    global $LANGUAGE;
    return $LANGUAGE->getCode();
  }

  function SHORT_LANG_CODE() {
    if(!array_key_exists('LANGUAGE', $GLOBALS))
      return "SHORT_LANG_CODE";

    global $LANGUAGE;
    return $LANGUAGE->getShortCode();
  }
}
