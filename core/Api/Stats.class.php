<?php

namespace Api;

use Driver\SQL\Condition\Compare;
use Driver\SQL\Condition\CondBool;

class Stats extends Request {

  private bool $mailConfigured;
  private bool $recaptchaConfigured;

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array());
  }

  private function getUserCount() {
    $sql = $this->user->getSQL();
    $res = $sql->select($sql->count())->from("User")->execute();
    $this->success = $this->success && ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    return ($this->success ? $res[0]["count"] : 0);
  }

  private function getPageCount() {
    $sql = $this->user->getSQL();
    $res = $sql->select($sql->count())->from("Route")
      ->where(new CondBool("active"))
      ->execute();
    $this->success = $this->success && ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    return ($this->success ? $res[0]["count"] : 0);
  }

  private function checkSettings() {
    $req = new \Api\Settings\Get($this->user);
    $this->success = $req->execute(array("key" => "^(mail_enabled|recaptcha_enabled)$"));
    $this->lastError = $req->getLastError();

    if ($this->success) {
      $settings = $req->getResult()["settings"];
      $this->mailConfigured = ($settings["mail_enabled"] ?? "0") === "1";
      $this->recaptchaConfigured = ($settings["recaptcha_enabled"] ?? "0") === "1";
    }

    return $this->success;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $userCount = $this->getUserCount();
    $pageCount = $this->getPageCount();
    $req = new \Api\Visitors\Stats($this->user);
    $this->success = $req->execute(array("type"=>"monthly"));
    $this->lastError = $req->getLastError();
    if (!$this->success) {
      return false;
    }

    $visitorStatistics = $req->getResult()["visitors"];
    $loadAvg = "Unknown";
    if (function_exists("sys_getloadavg")) {
      $loadAvg = sys_getloadavg();
    }

    if (!$this->checkSettings()) {
      return false;
    }

    $this->result["userCount"] = $userCount;
    $this->result["pageCount"] = $pageCount;
    $this->result["visitors"] = $visitorStatistics;
    $this->result["server"] = array(
      "version" => WEBBASE_VERSION,
      "server" => $_SERVER["SERVER_SOFTWARE"] ?? "Unknown",
      "memory_usage" => memory_get_usage(),
      "load_avg" => $loadAvg,
      "database" => $this->user->getSQL()->getStatus(),
      "mail" => $this->mailConfigured,
      "reCaptcha" => $this->recaptchaConfigured
    );

    return $this->success;
  }

}