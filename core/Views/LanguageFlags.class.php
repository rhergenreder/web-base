<?php

namespace Views;

class LanguageFlags extends \View {

  public function __construct($document) {
    parent::__construct($document);
  }

  public function getCode() {

    $requestUri = $_SERVER["REQUEST_URI"];
    $queryString = $_SERVER['QUERY_STRING'];

    $flags = array();
    $request = new \Api\GetLanguages($this->getDocument()->getUser());
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
    if($request->execute()) {
      foreach($request->getResult()['languages'] as $lang) {
        $langCode = $lang['code'];
        $langName = $lang['name'];
        $query['lang'] = $langCode;
        $queryString = http_build_query($query);

        $flags[] = $this->createLink(
          "$url$queryString",
          "<img src=\"/img/icons/lang/$langCode.gif\" alt=\"$langName\" title=\"$langName\">"
        );
      }
    } else {
      $flags[] = $this->createErrorText($request->getLastError());
    }

    return implode('', $flags);
  }
}

?>
