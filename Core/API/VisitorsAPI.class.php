<?php

namespace Core\API {

  use Core\Objects\Context;

  abstract class VisitorsAPI extends Request {
    public function __construct(Context $context, bool $externalCall = false, array $params = []) {
      parent::__construct($context, $externalCall, $params);
    }
  }
}

namespace Core\API\Visitors {

  use Core\API\Parameter\Parameter;
  use Core\API\Parameter\StringType;
  use Core\API\VisitorsAPI;
  use DateTime;
  use Core\Driver\SQL\Condition\Compare;
  use Core\Driver\SQL\Expression\Add;
  use Core\Driver\SQL\Query\Select;
  use Core\Driver\SQL\Strategy\UpdateStrategy;
  use Core\Objects\Context;

  class ProcessVisit extends VisitorsAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        "cookie" => new StringType("cookie")
      ));
      $this->isPublic = false;
    }

    public function _execute(): bool {
      $sql = $this->context->getSQL();
      $cookie = $this->getParam("cookie");
      $day = (new DateTime())->format("Ymd");
      $sql->insert("Visitor", array("cookie", "day"))
        ->addRow($cookie, $day)
        ->onDuplicateKeyStrategy(new UpdateStrategy(
          array("day", "cookie"),
          array("count" => new Add("Visitor.count", 1))))
        ->execute();

      return $this->success;
    }
  }

  class Stats extends VisitorsAPI {
    public function __construct(Context $context, bool $externalCall = false) {
      parent::__construct($context, $externalCall, array(
        'type' => new StringType('type', 32),
        'date' => new Parameter('date', Parameter::TYPE_DATE, true, new DateTime())
      ));
    }

    private function setConditions(string $type, DateTime $date, Select $query): bool {
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
        return $this->createError("Invalid scope: $type");
      }

      return true;
    }

    public function _execute(): bool {
      $date = $this->getParam("date");
      $type = $this->getParam("type");

      $sql = $this->context->getSQL();
      $query = $sql->select($sql->count(), "day")
        ->from("Visitor")
        ->where(new Compare("count", 1, ">"))
        ->groupBy("day")
        ->orderBy("day")
        ->ascending();

      $this->success = $this->setConditions($type, $date, $query);
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