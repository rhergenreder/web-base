<?php

namespace Views;

use Api\GetLanguages;
use Elements\View;

class LanguageFlags extends View {

  private array $languageFlags;

  public function __construct($document) {
    parent::__construct($document);
    $this->languageFlags = array();
  }

  public function loadView() {
    parent::loadView();

    $request = new GetLanguages($this->getDocument()->getUser());
    if($request->execute()) {

      $requestUri = $_SERVER["REQUEST_URI"];
      $queryString = $_SERVER['QUERY_STRING'];

      $params = explode("&", $queryString);
      $query = array();
      foreach($params as $param) {
        $aParam = explode("=", $param);
        $key = $aParam[0];

        if($key == "s" && startsWith($requestUri, "/s/"))
          continue;

        $val = (isset($aParam[1]) ? $aParam[1] : "");
        if(!empty($key)) {
          $query[$key] = $val;
        }
      }

      $url = parse_url($requestUri, PHP_URL_PATH) . "?";

      foreach($request->getResult()['languages'] as $lang) {
        $langCode = $lang['code'];
        $langName = $lang['name'];
        $query['lang'] = $langCode;
        $queryString = http_build_query($query);

        $this->languageFlags[] = $this->createLink(
          "$url$queryString",
          "<img class=\"p-1\" src=\"/img/icons/lang/$langCode.gif\" alt=\"$langName\" title=\"$langName\">"
        );
      }
    }
  }

  public function getCode() {
    return implode('', $this->languageFlags);
  }
}