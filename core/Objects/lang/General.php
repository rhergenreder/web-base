<?php

require_once './LanguageModule.php';

abstract class CLanguageModuleGeneral {

  public static function getEntries($langCode) {
    switch($langCode) {
      case "de_DE":
        $this->entries[""] = "";
        break;      
    }
  }

}

?>
