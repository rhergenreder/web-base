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
  use Core\API\Parameter\Parameter;
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
        'langId' => new Parameter('langId', Parameter::TYPE_INT, true, NULL),
        'langCode' => new StringType('langCode', 5, true, NULL),
      ));

    }

    private function checkLanguage(): bool {
      $langId = $this->getParam("langId");
      $langCode = $this->getParam("langCode");

      if (is_null($langId) && is_null($langCode)) {
        return $this->createError(L("Either langId or langCode must be given"));
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

    private function updateLanguage(): bool {
      $languageId = $this->language->getId();
      $userId = $this->context->getUser()->getId();
      $sql = $this->context->getSQL();

      $this->success = $sql->update("User")
        ->set("language_id", $languageId)
        ->whereEq("id", $userId)
        ->execute();

      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    public function _execute(): bool {
      if (!$this->checkLanguage())
        return false;

      if ($this->context->getSession()) {
        $this->updateLanguage();
      }

      $this->context->setLanguage($this->language);
      return $this->success;
    }
  }
}