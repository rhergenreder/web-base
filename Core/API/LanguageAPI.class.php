<?php

namespace Core\API {

  use Core\Objects\Context;

  abstract class LanguageAPI extends Request {
    public function __construct(Context $context, bool $externalCall = false, array $params = array()) {
      parent::__construct($context, $externalCall, $params);
    }
  }
}

namespace Core\API\Language {

  use Core\API\LanguageAPI;
  use Core\API\Parameter\ArrayType;
  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\RegexType;
  use Core\API\Parameter\StringType;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Condition\CondOr;
  use Core\Objects\Context;
  use Core\Objects\DatabaseEntity\Language;

  class Get extends LanguageAPI {

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array());
    }

    public function _execute(): bool {
      $sql = $this->context->getSQL();
      $languages = Language::findAll($sql);
      $this->success = ($languages !== null);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result['languages'] = [];
        if (count($languages) === 0) {
          $this->lastError = L("No languages found");
        } else {
          foreach ($languages as $language) {
            $this->result['languages'][$language->getId()] = $language->jsonSerialize();
          }
        }
      }

      return $this->success;
    }
  }

  class Set extends LanguageAPI {

    private Language $language;

    public function __construct(Context $context, $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'id' => new Parameter('id', Parameter::TYPE_INT, true, NULL),
        'code' => new StringType('code', 5, true, NULL),
      ));

    }

    private function checkLanguage(): bool {
      $langId = $this->getParam("id");
      $langCode = $this->getParam("code");

      if (is_null($langId) && is_null($langCode)) {
        return $this->createError(L("Either 'id' or 'code' must be given"));
      }

      $sql = $this->context->getSQL();
      $languages = Language::findAll($sql,
        new CondOr(new Compare("id", $langId), new Compare("code", $langCode))
      );

      $this->success = ($languages !== null);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        if (count($languages) === 0) {
          return $this->createError(L("This Language does not exist"));
        } else {
          $this->language = array_shift($languages);
        }
      }

      return $this->success;
    }

    private function updateLanguage(): void {
      $sql = $this->context->getSQL();
      $currentUser = $this->context->getUser();
      $currentUser->language = $this->language;
      $this->success = $currentUser->save($sql, ["language"]);
      $this->lastError = $sql->getLastError();
    }

    public function _execute(): bool {
      if (!$this->checkLanguage())
        return false;

      if ($this->context->getSession()) {
        $this->updateLanguage();
      }

      $this->context->setLanguage($this->language);
      $this->result["language"] = $this->language->jsonSerialize();
      return $this->success;
    }
  }

  class GetEntries extends LanguageAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, [
        "code" => new RegexType("code", Language::LANG_CODE_PATTERN, true, NULL),
        "modules" => new ArrayType("modules", Parameter::TYPE_STRING, true, false),
        "compression" => new StringType("compression", -1, true, NULL, ["gzip", "zlib"])
      ]);
      $this->loginRequired = false;
      $this->csrfTokenRequired = false;
    }

    protected function _execute(): bool {
      $code = $this->getParam("code");
      if ($code === null) {
        $code = $this->context->getLanguage()->getCode();
      }

      $entries = [];
      $modulePaths = [];
      $requestedModules = $this->getParam("modules");
      foreach ($requestedModules as $module) {
        if (!preg_match(Language::LANG_MODULE_PATTERN, $module)) {
          return $this->createError("Invalid module name: $module");
        }

        $moduleFound = false;
        foreach (["Core", "Site"] as $baseDir) {
          $filePath = realpath(implode("/", [WEBROOT, $baseDir, "Localization", $code, "$module.php"]));
          if ($filePath && is_file($filePath)) {
            $moduleFound = true;
            $moduleEntries = @include_once $filePath;
            $entries[$module] = array_merge($entries[$module] ?? [], $moduleEntries);
            break;
          }
        }

        if (!$moduleFound) {
          return $this->createError("Module not found: $module");
        }
      }

      $this->result["code"] = $code;

      $compression = $this->getParam("compression");
      if ($compression) {
        switch ($compression) {
          case "gzip":
            $this->result["compressed"] = base64_encode(gzencode(json_encode($entries), 9));
            break;
          case "zlib":
            $this->result["compressed"] = base64_encode(gzcompress(json_encode($entries), 9, ZLIB_ENCODING_DEFLATE));
            break;
          default:
            http_response_code(400);
            return $this->createError("Invalid compression method: $compression");
        }
      } else {
        $this->result["entries"] = $entries;
      }
      return true;
    }
  }
}