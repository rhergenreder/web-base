<?php

namespace Api {

  abstract class VisitorsAPI extends Request {

  }
}

namespace Api\Visitors {

  use Api\Parameter\Parameter;
  use Api\Parameter\StringType;
  use Api\VisitorsAPI;
  use DateTime;
  use Driver\SQL\Condition\Compare;
  use Driver\SQL\Query\Select;
  use Objects\User;

  class Stats extends VisitorsAPI {
    public function __construct(User $user, bool $externalCall = false) {
      parent::__construct($user, $externalCall, array(
        'type' => new StringType('type', 32),
        'date' => new Parameter('date', Parameter::TYPE_DATE, true, new DateTime())
      ));
    }

    private function setConditions(string $type, DateTime $date, Select $query) {
      if ($type === "yearly") {
        $yearStart = $date->format("Y0000");
        $yearEnd = $date->modify("+1 year")->format("Y0000");
        $query->where(new Compare("day", $yearStart, ">="));
        $query->where(new Compare("day", $yearEnd, "<"));
      } else if($type === "monthly") {
        $monthStart = $date->format("Ym00");
        $monthEnd = $date->modify("+1 month")->format("Ym00");
        $query->where(new Compare("day", $monthStart, ">="));
        $query->where(new Compare("day", $monthEnd, "<"));
      } else if($type === "weekly") {
        $weekStart = ($date->modify("monday this week"))->format("Ymd");
        $weekEnd = ($date->modify("sunday this week"))->format("Ymd");
        $query->where(new Compare("day", $weekStart, ">="));
        $query->where(new Compare("day", $weekEnd, "<="));
      } else {
        $this->createError("Invalid scope: $type");
      }
    }

    public function execute($values = array()) {
      if (!parent::execute($values)) {
        return false;
      }

      $date = $this->getParam("date");
      $type = $this->getParam("type");

      $sql = $this->user->getSQL();
      $query = $sql->select($sql->count(), "day")
        ->from("Visitor")
        ->where(new Compare("count", 1, ">"))
        ->groupBy("day")
        ->orderBy("day")
        ->ascending();

      $this->setConditions($type, $date, $query);
      if (!$this->success) {
        return false;
      }

      $res = $query->execute();
      $this->success = ($res !== FALSE);
      $this->lastError = $sql->getLastError();

      if ($this->success) {
        $this->result["type"] = $type;
        $this->result["visitors"] = array();

        foreach($res as $row) {
          $day = DateTime::createFromFormat("Ymd", $row["day"])->format("Y/m/d");
          $count = $row["count"];
          $this->result["visitors"][$day] = $count;
        }
      }

      return $this->success;
    }
  }
}