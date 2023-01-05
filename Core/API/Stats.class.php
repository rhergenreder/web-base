<?php

namespace Core\API;

use Core\Driver\SQL\Expression\Count;
use Core\Driver\SQL\Expression\Distinct;
use DateTime;
use Core\Driver\SQL\Condition\Compare;
use Core\Driver\SQL\Condition\CondBool;
use Core\Objects\Context;

class Stats extends Request {

  private bool $mailConfigured;
  private bool $recaptchaConfigured;

  public function __construct(Context $context, $externalCall = false) {
    parent::__construct($context, $externalCall, array());
  }

  private function getUserCount(): int {
    $sql = $this->context->getSQL();
    $res = $sql->select(new Count())->from("User")->execute();
    $this->success = $this->success && ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    return ($this->success ? intval($res[0]["count"]) : 0);
  }

  private function getPageCount(): int {
    $sql = $this->context->getSQL();
    $res = $sql->select(new Count())->from("Route")
      ->where(new CondBool("active"))
      ->execute();
    $this->success = $this->success && ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    return ($this->success ? intval($res[0]["count"]) : 0);
  }

  private function checkSettings(): bool {
    $req = new \Core\API\Settings\Get($this->context);
    $this->success = $req->execute(array("key" => "^(mail_enabled|recaptcha_enabled)$"));
    $this->lastError = $req->getLastError();

    if ($this->success) {
      $settings = $req->getResult()["settings"];
      $this->mailConfigured = ($settings["mail_enabled"] ?? "0") === "1";
      $this->recaptchaConfigured = ($settings["recaptcha_enabled"] ?? "0") === "1";
    }

    return $this->success;
  }

  private function getVisitorCount() {
    $sql = $this->context->getSQL();
    $date = new DateTime();
    $monthStart = $date->format("Ym00");
    $monthEnd = $date->modify("+1 month")->format("Ym00");
    $res = $sql->select(new Count(new Distinct("cookie")))
      ->from("Visitor")
      ->where(new Compare("day", $monthStart, ">="))
      ->where(new Compare("day", $monthEnd, "<"))
      ->where(new Compare("count", 2, ">="))
      ->execute();

    $this->success = ($res !== false);
    $this->lastError = $sql->getLastError();
    return ($this->success ? $res[0]["count"] : $this->success);
  }

  public function _execute(): bool {
    $userCount = $this->getUserCount();
    $pageCount = $this->getPageCount();
    $req = new \Core\API\Visitors\Stats($this->context);
    $this->success = $req->execute(array("type"=>"monthly"));
    $this->lastError = $req->getLastError();
    if (!$this->success) {
      return false;
    }

    $visitorStatistics = $req->getResult()["visitors"];
    $visitorCount = $this->getVisitorCount();
    if (!$this->success) {
      return false;
    }

    $loadAvg = "Unknown";
    if (function_exists("sys_getloadavg")) {
      $loadAvg = sys_getloadavg();
    }

    if (!$this->checkSettings()) {
      return false;
    }

    $this->result["data"] = [
      "userCount" => $userCount,
      "pageCount" => $pageCount,
      "visitors" => $visitorStatistics,
      "visitorsTotal" => $visitorCount,
      "server" => [
        "version" => WEBBASE_VERSION,
        "server" => $_SERVER["SERVER_SOFTWARE"] ?? "Unknown",
        "memory_usage" => memory_get_usage(),
        "load_avg" => $loadAvg,
        "database" => $this->context->getSQL()->getStatus(),
        "mail" => $this->mailConfigured,
        "reCaptcha" => $this->recaptchaConfigured
      ],
    ];
    
    return $this->success;
  }

}