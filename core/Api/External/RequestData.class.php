<?php

namespace Api\External;
use \Api\Parameter\Parameter;
use \Api\Parameter\StringType;

class RequestData extends \Api\Request {

  public function __construct($user, $externCall = false) {
    parent::__construct($user, $externCall, array(
      "url" => new StringType("url", 256)
    ));
    $this->isPublic = false;
  }

  private function requestURL() {
    $url = $this->getParam("url");

    $ckfile = tempnam("/tmp", 'cookiename');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $ckfile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $ckfile);
    $data = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $success = false;
    if(curl_errno($ch)) {
      $this->lastError = curl_error($ch);
    } else if($statusCode != 200) {
      $this->lastError = "External Site returned status code: " . $statusCode;
    } else {
      $this->result["data"] = $data;
      $this->result["cached"] = false;
      $success = true;
    }

    unlink($ckfile);
    curl_close ($ch);
    return $success;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $url = $this->getParam("url");
    $expires = $this->getParam("expires");

    $query = "SELECT data, expires FROM ExternalSiteCache WHERE url=?";
    $req = new \Api\ExecuteSelect($this->user);
    $this->success = $req->execute(array("query" => $query, $url));
    $this->lastError = $req->getLastError();

    if($this->success) {
      $mustRevalidate = true;

      if(!empty($req->getResult()['rows'])) {
        $row = $req->getResult()['rows'][0];
        if($row["expires"] == null || !isinPast($row["expires"])) {
          $mustRevalidate = false;
          $this->result["data"] = $row["data"];
          $this->result["expires"] = $row["expires"];
          $this->result["cached"] = true;
        }
      }

      if($mustRevalidate) {
        $this->success = $this->requestURL();
      }
    }

    return $this->success;
  }
};

?>
