<?php

namespace Api {

  class LanguageAPI extends Request {

  }

}

namespace Api\Language {

  use Api\LanguageAPI;
  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Condition\CondOr;
  use Objects\Language;

  class Get extends LanguageAPI {

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array());
    }

    public function execute($values = array()) {
      if(!parent::execute($values)) {
        return false;
      }

      $sql = $this->user->getSQL();
      $res = $sql->select("uid", "code", "name")
        ->from("Language")
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if($this->success) {
        $this->result['languages'] = array();
        if(empty($res) === 0) {
          $this->lastError = L("No languages found");
        } else {
          foreach($res as $row) {
            $this->result['languages'][$row['uid']] = $row;
          }
        }
      }

      return $this->success;
    }
  }

  class Set extends LanguageAPI {

    private Language $language;

    public function __construct($user, $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'langId' => new Parameter('langId', Parameter::TYPE_INT, true, NULL),
        'langCode' => new StringType('langCode', 5, true, NULL),
      ));
      $this->csrfTokenRequired = true;
    }

    private function checkLanguage() {
      $langId = $this->getParam("langId");
      $langCode = $this->getParam("langCode");

      if(is_null($langId) && is_null($langCode)) {
        return $this->createError(L("Either langId or langCode must be given"));
      }

      $res = $this->user->getSQL()
        ->select("uid", "code", "name")
        ->from("Language")
        ->where(new CondOr(new Compare("uid", $langId), new Compare("code", $langCode)))
        ->execute();

      $this->success = ($res !== FALSE);
      $this->lastError = $this->user->getSQL()->getLastError();

      if ($this->success) {
        if(count($res) == 0) {
          return $this->createError(L("This Language does not exist"));
        } else {
          $row = $res[0];
          $this->language = Language::newInstance($row['uid'], $row['code'], $row['name']);
          if(!$this->language) {
            return $this->createError(L("Error while loading language"));
          }
        }
      }

      return $this->success;
    }

    private function updateLanguage() {
      $languageId = $this->language->getId();
      $userId = $this->user->getId();
      $sql = $this->user->getSQL();

      $this->success = $sql->update("User")
        ->set("language_id", $languageId)
        ->where(new Compare("uid", $userId))
        ->execute();
      $this->lastError = $sql->getLastError();
      return $this->success;
    }

    public function execute($values = array()) {
      if(!parent::execute($values)) {
        return false;
      }

      if(!$this->checkLanguage())
        return false;

      if($this->user->isLoggedIn()) {
        $this->updateLanguage();
      }

      $this->user->setLanguage($this->language);
      return $this->success;
    }
  }

}