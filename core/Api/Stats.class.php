<?php

namespace Api;

use Driver\SQL\Condition\Compare;

class Stats extends Request {

  public function __construct($user, $externalCall = false) {
    parent::__construct($user, $externalCall, array());
    $this->csrfTokenRequired = true;
    $this->loginRequired = true;
    $this->requiredGroup = USER_GROUP_ADMIN;
  }

  private function getUserCount() {
    $sql = $this->user->getSQL();
    $res = $sql->select($sql->count())->from("User")->execute();
    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    return ($this->success ? $res[0]["count"] : 0);
  }

  private function getPageCount() {
    return 0;
  }

  private function getVisitorStatistics() {

    $currentYear = getYear();
    $firstMonth = $currentYear * 100 + 01;
    $latsMonth  = $currentYear * 100 + 12;

    $sql = $this->user->getSQL();
    $res = $sql->select($sql->count(), "month")
      ->from("Visitor")
      ->where(new Compare("month", $firstMonth, ">="))
      ->where(new Compare("month", $latsMonth, "<="))
      ->where(new Compare("count", 1, ">"))
      ->groupBy("month")
      ->orderBy("month")
      ->ascending()
      ->execute();

    $this->success = ($res !== FALSE);
    $this->lastError = $sql->getLastError();

    $visitors = array();

    if ($this->success) {
      foreach($res as $row) {
        $month = $row["month"];
        $count = $row["count"];
        $visitors[$month] = $count;
      }
    }

    return $visitors;
  }

  public function execute($values = array()) {
    if(!parent::execute($values)) {
      return false;
    }

    $userCount = $this->getUserCount();
    $pageCount = $this->getPageCount();
    $visitorStatistics = $this->getVisitorStatistics();

    if ($this->success) {
      $this->result["userCount"] = $userCount;
      $this->result["pageCount"] = $pageCount;
      $this->result["visitors"] = $visitorStatistics;
    }

    return $this->success;
  }

}