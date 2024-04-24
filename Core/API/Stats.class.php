<?php

namespace Core\API;

use Core\Objects\DatabaseEntity\Group;
use Core\Objects\DatabaseEntity\Route;
use Core\Objects\DatabaseEntity\User;
use Core\Driver\SQL\Condition\CondBool;
use Core\Objects\Context;

class Stats extends Request {

  public function __construct(Context $context, $externalCall = false) {
    parent::__construct($context, $externalCall, array());
  }

  public function _execute(): bool {
    $settings = $this->context->getSettings();
    $sql = $this->context->getSQL();
    $userCount = User::count($sql);
    $pageCount = Route::count($sql, new CondBool("active"));
    $groupCount = Group::count($sql);

    $req = new \Core\API\Logs\Get($this->context, false);
    $success = $req->execute([
      "since" => (new \DateTime())->modify("-48 hours"),
      "severity" => "error"
    ]);

    if ($success) {
      $errorCount = $req->getResult()["pagination"]["total"];
    } else {
      $errorCount = "Unknown";
    }

    $loadAvg = "Unknown";
    if (function_exists("sys_getloadavg")) {
      $loadAvg = sys_getloadavg();
    }

    $this->result["data"] = [
      "userCount" => $userCount,
      "pageCount" => $pageCount,
      "groupCount" => $groupCount,
      "errorCount" => $errorCount,
      "server" => [
        "version" => WEBBASE_VERSION,
        "server" => $_SERVER["SERVER_SOFTWARE"] ?? "Unknown",
        "memoryUsage" => memory_get_usage(),
        "loadAverage" => $loadAvg,
        "database" => $this->context->getSQL()->getStatus(),
        "mail" => $settings->isMailEnabled(),
        "captcha" => $settings->getCaptchaProvider()?->jsonSerialize(),
        "rateLimiting" => $settings->isRateLimitingEnabled()
      ],
    ];
    
    return $this->success;
  }

  public static function getDescription(): string {
    return "Allows users to view site statistics";
  }

  public static function getDefaultPermittedGroups(): array {
    return [Group::ADMIN, Group::SUPPORT];
  }
}