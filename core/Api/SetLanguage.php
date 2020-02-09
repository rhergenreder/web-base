<?php

namespace Api;

use Api\Parameter\Parameter;
use Api\Parameter\StringType;

class SetLanguage extends Request {

  private $language;

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array(
      'langId' => new Parameter('langId', Parameter::TYPE_INT, true, NULL),
      'langCode' => new StringType('langCode', 5, true, NULL),
    ));
  }

  private function checkLanguage() {
    $langId = $this->getParam("langId");
    $langCode = $this->getParam("langCode");

    if(is_null($langId) && is_null($langCode)) {
      return $this->createError(L("Either langId or langCode must be given"));
    }

    $query = "SELECT uid, code, name FROM Language WHERE uid=? OR code=?";
    $request = new ExecuteSelect($this->user);
    $this->success = $request->execute(array("query" => $query, $langId, $langCode));
    $this->lastError = $request->getLastError();

    if($this->success) {
      if(count($request->getResult()['rows']) == 0) {
        return $this->createError(L("This Language does not exist"));
      } else {
        $row = $request->getResult()['rows'][0];
        $this->language = \Objects\Language::newInstance($row['uid'], $row['code'], $row['name']);
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

    $query = "UPDATE User SET uidLanguage = ? WHERE uid = ?";
    $request = new ExecuteStatement($this->user);
    $this->success = $request->execute(array("query" => $query, $languageId, $userId));
    $this->lastError = $request->getLastError();
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

    $this->user->setLangauge($this->language);
    return $this->success;
  }
};

?>
